<?php

namespace App\Services;

use App\Models\EventContent;
use App\Models\ActivityCompletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EventService extends ContentService
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->activityType = 'event';
        $this->modelClass = EventContent::class;
        
        $this->validationRules = [
            'activity_id' => 'required|integer|exists:activities,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'location' => 'nullable|string',
            'is_virtual' => 'boolean',
            'virtual_meeting_link' => 'nullable|string|url',
            'virtual_meeting_platform' => 'nullable|string',
            'timezone' => 'nullable|string',
            'max_participants' => 'nullable|integer|min:1',
            'registration_required' => 'boolean',
            'registration_deadline' => 'nullable|date|before:start_date',
            'speakers' => 'nullable|array',
            'speakers.*.name' => 'required|string',
            'speakers.*.bio' => 'nullable|string',
            'speakers.*.photo' => 'nullable|string',
            'speakers.*.organization' => 'nullable|string',
            'agenda' => 'nullable|array',
            'agenda.*.time' => 'required|string',
            'agenda.*.title' => 'required|string',
            'agenda.*.description' => 'nullable|string',
            'agenda.*.speaker' => 'nullable|string',
            'resources' => 'nullable|array',
            'resources.*.title' => 'required|string',
            'resources.*.type' => 'required|string',
            'resources.*.url' => 'required|string',
            'categories' => 'nullable|array',
            'categories.*' => 'string',
            'attendance_tracking' => 'boolean',
            'certificate_provided' => 'boolean',
            'metadata' => 'nullable|array'
        ];
    }
    
    /**
     * Process data before saving to the database
     * Encode arrays to JSON
     *
     * @param array $data
     * @return array
     */
    protected function processDataBeforeSave(array $data): array
    {
        if (isset($data['speakers']) && is_array($data['speakers'])) {
            $data['speakers'] = json_encode($data['speakers']);
        }
        
        if (isset($data['agenda']) && is_array($data['agenda'])) {
            $data['agenda'] = json_encode($data['agenda']);
        }
        
        if (isset($data['resources']) && is_array($data['resources'])) {
            $data['resources'] = json_encode($data['resources']);
        }
        
        if (isset($data['categories']) && is_array($data['categories'])) {
            $data['categories'] = json_encode($data['categories']);
        }
        
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        
        return $data;
    }
    
    /**
     * Process data after retrieving from the database
     * Decode JSON to arrays
     *
     * @param Model $model
     * @return Model
     */
    protected function processDataAfterRetrieve(Model $model): Model
    {
        if (isset($model->speakers) && is_string($model->speakers)) {
            $model->speakers = json_decode($model->speakers, true);
        }
        
        if (isset($model->agenda) && is_string($model->agenda)) {
            $model->agenda = json_decode($model->agenda, true);
        }
        
        if (isset($model->resources) && is_string($model->resources)) {
            $model->resources = json_decode($model->resources, true);
        }
        
        if (isset($model->categories) && is_string($model->categories)) {
            $model->categories = json_decode($model->categories, true);
        }
        
        if (isset($model->metadata) && is_string($model->metadata)) {
            $model->metadata = json_decode($model->metadata, true);
        }
        
        return $model;
    }
    
    /**
     * Get event content by ID with decoded data
     *
     * @param int $id
     * @return Model|null
     */
    public function getEvent(int $id): ?Model
    {
        $content = $this->getById($id);
        
        if ($content) {
            return $this->processDataAfterRetrieve($content);
        }
        
        return null;
    }
    
    /**
     * Register a participant for an event
     *
     * @param int $eventId
     * @param int $enrollmentId
     * @param array $registrationData
     * @return array
     */
    public function registerParticipant(int $eventId, int $enrollmentId, array $registrationData = []): array
    {
        $event = $this->getEvent($eventId);
        
        if (!$event) {
            return [
                'success' => false,
                'message' => 'Event not found'
            ];
        }
        
        // Check if registration is required
        if (!$event->registration_required) {
            return [
                'success' => false,
                'message' => 'Registration is not required for this event'
            ];
        }
        
        // Check if registration deadline has passed
        if (isset($event->registration_deadline) && strtotime($event->registration_deadline) < time()) {
            return [
                'success' => false,
                'message' => 'Registration deadline has passed'
            ];
        }
        
        // Check if event has already started
        if (strtotime($event->start_date) < time()) {
            return [
                'success' => false,
                'message' => 'Event has already started, registration is closed'
            ];
        }
        
        // Check if maximum participants limit has been reached
        if (isset($event->max_participants)) {
            $activityId = $event->activity_id;
            
            $registeredCount = ActivityCompletion::where('activity_id', $activityId)
                ->where('status', 'registered')
                ->count();
            
            if ($registeredCount >= $event->max_participants) {
                return [
                    'success' => false,
                    'message' => 'Event has reached maximum number of participants'
                ];
            }
        }
        
        // Get activity ID
        $activityId = $event->activity_id;
        
        // Check if already registered
        $existing = ActivityCompletion::where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->first();
        
        if ($existing && $existing->status === 'registered') {
            return [
                'success' => false,
                'message' => 'Already registered for this event',
                'registration' => $existing
            ];
        }
        
        // Create or update registration
        $registration = [
            'registration_date' => now(),
            'participant_info' => $registrationData
        ];
        
        if ($existing) {
            $existing->update([
                'status' => 'registered',
                'data' => json_encode($registration)
            ]);
            $completion = $existing;
        } else {
            $completion = ActivityCompletion::create([
                'enrollment_id' => $enrollmentId,
                'activity_id' => $activityId,
                'status' => 'registered',
                'data' => json_encode($registration)
            ]);
        }
        
        return [
            'success' => true,
            'message' => 'Successfully registered for the event',
            'registration' => $completion,
            'event' => $event
        ];
    }
    
    /**
     * Cancel registration for an event
     *
     * @param int $eventId
     * @param int $enrollmentId
     * @return array
     */
    public function cancelRegistration(int $eventId, int $enrollmentId): array
    {
        $event = $this->getEvent($eventId);
        
        if (!$event) {
            return [
                'success' => false,
                'message' => 'Event not found'
            ];
        }
        
        // Get activity ID
        $activityId = $event->activity_id;
        
        // Check if registered
        $registration = ActivityCompletion::where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->where('status', 'registered')
            ->first();
        
        if (!$registration) {
            return [
                'success' => false,
                'message' => 'Not registered for this event'
            ];
        }
        
        // Check if event has already started
        if (strtotime($event->start_date) < time()) {
            return [
                'success' => false,
                'message' => 'Event has already started, cannot cancel registration'
            ];
        }
        
        // Update registration status
        $registration->update([
            'status' => 'cancelled'
        ]);
        
        return [
            'success' => true,
            'message' => 'Registration cancelled successfully'
        ];
    }
    
    /**
     * Mark attendance for an event
     *
     * @param int $eventId
     * @param int $enrollmentId
     * @param bool $attended
     * @return array
     */
    public function markAttendance(int $eventId, int $enrollmentId, bool $attended = true): array
    {
        $event = $this->getEvent($eventId);
        
        if (!$event) {
            return [
                'success' => false,
                'message' => 'Event not found'
            ];
        }
        
        // Check if attendance tracking is enabled
        if (!$event->attendance_tracking) {
            return [
                'success' => false,
                'message' => 'Attendance tracking is not enabled for this event'
            ];
        }
        
        // Get activity ID
        $activityId = $event->activity_id;
        
        // Check if registered
        $registration = ActivityCompletion::where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->first();
        
        if (!$registration) {
            return [
                'success' => false,
                'message' => 'Not registered for this event'
            ];
        }
        
        // Update attendance status
        $registrationData = json_decode($registration->data ?? '{}', true);
        $registrationData['attended'] = $attended;
        $registrationData['attendance_marked_at'] = now();
        
        $registration->update([
            'status' => $attended ? 'completed' : 'registered',
            'completed_at' => $attended ? now() : null,
            'data' => json_encode($registrationData)
        ]);
        
        return [
            'success' => true,
            'message' => $attended ? 'Attendance marked successfully' : 'Non-attendance marked successfully',
            'registration' => $registration
        ];
    }
    
    /**
     * Get all registered participants for an event
     *
     * @param int $eventId
     * @return array
     */
    public function getParticipants(int $eventId): array
    {
        $event = $this->getEvent($eventId);
        
        if (!$event) {
            return [
                'success' => false,
                'message' => 'Event not found'
            ];
        }
        
        // Get activity ID
        $activityId = $event->activity_id;
        
        // Get all registrations
        $registrations = ActivityCompletion::with('enrollment.user')
            ->where('activity_id', $activityId)
            ->whereIn('status', ['registered', 'completed'])
            ->get();
        
        $participants = [];
        
        foreach ($registrations as $registration) {
            $registrationData = json_decode($registration->data ?? '{}', true);
            
            $participants[] = [
                'user' => $registration->enrollment->user,
                'status' => $registration->status,
                'registration_date' => $registrationData['registration_date'] ?? null,
                'attended' => $registrationData['attended'] ?? false,
                'participant_info' => $registrationData['participant_info'] ?? []
            ];
        }
        
        return [
            'success' => true,
            'event' => $event,
            'participants' => $participants,
            'total_registered' => count($participants),
            'total_attended' => count(array_filter($participants, function($p) {
                return $p['attended'] === true;
            }))
        ];
    }
    
    /**
     * Get upcoming events
     *
     * @param int $limit
     * @return array
     */
    public function getUpcomingEvents(int $limit = 10): array
    {
        $events = EventContent::where('start_date', '>', now())
            ->orderBy('start_date', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($event) {
                return $this->processDataAfterRetrieve($event);
            });
        
        return $events->toArray();
    }
    
    /**
     * Get past events
     *
     * @param int $limit
     * @return array
     */
    public function getPastEvents(int $limit = 10): array
    {
        $events = EventContent::where('end_date', '<', now())
            ->orderBy('end_date', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($event) {
                return $this->processDataAfterRetrieve($event);
            });
        
        return $events->toArray();
    }
    
    /**
     * Get events by category
     *
     * @param string $category
     * @return array
     */
    public function getEventsByCategory(string $category): array
    {
        $events = [];
        $allEvents = EventContent::all();
        
        foreach ($allEvents as $event) {
            $categories = json_decode($event->categories ?? '[]', true);
            
            if (in_array($category, $categories)) {
                $events[] = $this->processDataAfterRetrieve($event);
            }
        }
        
        return $events;
    }
    
    /**
     * Add a speaker to event
     *
     * @param int $id
     * @param array $speaker
     * @return Model|null
     */
    public function addSpeaker(int $id, array $speaker): ?Model
    {
        $event = $this->getEvent($id);
        
        if (!$event) {
            return null;
        }
        
        $speakers = $event->speakers ?? [];
        $speakers[] = $speaker;
        
        return $this->update($id, ['speakers' => $speakers]);
    }
    
    /**
     * Remove a speaker from event
     *
     * @param int $id
     * @param string $speakerName
     * @return Model|null
     */
    public function removeSpeaker(int $id, string $speakerName): ?Model
    {
        $event = $this->getEvent($id);
        
        if (!$event || !isset($event->speakers)) {
            return null;
        }
        
        $speakers = array_filter($event->speakers, function ($speaker) use ($speakerName) {
            return $speaker['name'] !== $speakerName;
        });
        
        return $this->update($id, ['speakers' => array_values($speakers)]);
    }
    
    /**
     * Add an agenda item to event
     *
     * @param int $id
     * @param array $agendaItem
     * @return Model|null
     */
    public function addAgendaItem(int $id, array $agendaItem): ?Model
    {
        $event = $this->getEvent($id);
        
        if (!$event) {
            return null;
        }
        
        $agenda = $event->agenda ?? [];
        $agenda[] = $agendaItem;
        
        return $this->update($id, ['agenda' => $agenda]);
    }
    
    /**
     * Remove an agenda item from event
     *
     * @param int $id
     * @param int $itemIndex
     * @return Model|null
     */
    public function removeAgendaItem(int $id, int $itemIndex): ?Model
    {
        $event = $this->getEvent($id);
        
        if (!$event || !isset($event->agenda[$itemIndex])) {
            return null;
        }
        
        $agenda = $event->agenda;
        array_splice($agenda, $itemIndex, 1);
        
        return $this->update($id, ['agenda' => $agenda]);
    }
    
    /**
     * Add a resource to event
     *
     * @param int $id
     * @param array $resource
     * @return Model|null
     */
    public function addResource(int $id, array $resource): ?Model
    {
        $event = $this->getEvent($id);
        
        if (!$event) {
            return null;
        }
        
        $resources = $event->resources ?? [];
        $resources[] = $resource;
        
        return $this->update($id, ['resources' => $resources]);
    }
    
    /**
     * Remove a resource from event
     *
     * @param int $id
     * @param string $resourceTitle
     * @return Model|null
     */
    public function removeResource(int $id, string $resourceTitle): ?Model
    {
        $event = $this->getEvent($id);
        
        if (!$event || !isset($event->resources)) {
            return null;
        }
        
        $resources = array_filter($event->resources, function ($resource) use ($resourceTitle) {
            return $resource['title'] !== $resourceTitle;
        });
        
        return $this->update($id, ['resources' => array_values($resources)]);
    }
}

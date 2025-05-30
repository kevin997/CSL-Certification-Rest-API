<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\EnvironmentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /**
     * Get all teams for the current environment.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'environment_id' => 'required|exists:environments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $teams = Team::where('environment_id', $request->environment_id)
            ->withCount('teamMembers')
            ->get();

        return response()->json($teams);
    }

    /**
     * Create a new team.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'environment_id' => 'required|exists:environments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $team = DB::transaction(function () use ($request) {
            $team = Team::create([
                'name' => $request->name,
                'environment_id' => $request->environment_id,
                'personal_team' => false,
            ]);

            // Add the current user as a team admin
            $environmentUser = EnvironmentUser::where('user_id', auth()->id())
                ->where('environment_id', $request->environment_id)
                ->first();

            if ($environmentUser) {
                TeamMember::create([
                    'team_id' => $team->id,
                    'user_id' => auth()->id(),
                    'environment_id' => $request->environment_id,
                    'environment_user_id' => $environmentUser->id,
                    'role' => 'admin',
                    'joined_at' => now(),
                ]);
            }

            return $team;
        });

        return response()->json($team, 201);
    }

    /**
     * Get a specific team.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $team = Team::with(['teamMembers.user:id,name,email'])->findOrFail($id);
        
        return response()->json($team);
    }

    /**
     * Update a team.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $team = Team::findOrFail($id);
        
        // Check if user has permission to update this team
        $isTeamAdmin = TeamMember::where('team_id', $id)
            ->where('user_id', auth()->id())
            ->where('role', 'admin')
            ->exists();
            
        if (!$isTeamAdmin) {
            return response()->json(['error' => 'You do not have permission to update this team'], 403);
        }

        $team->update([
            'name' => $request->name,
        ]);

        return response()->json($team);
    }

    /**
     * Delete a team.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $team = Team::findOrFail($id);
        
        // Check if user has permission to delete this team
        $isTeamAdmin = TeamMember::where('team_id', $id)
            ->where('user_id', auth()->id())
            ->where('role', 'admin')
            ->exists();
            
        if (!$isTeamAdmin) {
            return response()->json(['error' => 'You do not have permission to delete this team'], 403);
        }

        // Delete team members
        TeamMember::where('team_id', $id)->delete();
        
        // Delete team invitations
        TeamInvitation::where('team_id', $id)->delete();
        
        // Delete the team
        $team->delete();

        return response()->json(['message' => 'Team deleted successfully']);
    }

    /**
     * Get all team members for a team.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTeamMembers($id)
    {
        $team = Team::findOrFail($id);
        
        $members = TeamMember::with('user:id,name,email')
            ->where('team_id', $id)
            ->get();
            
        return response()->json($members);
    }

    /**
     * Invite a user to a team.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function inviteMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|exists:teams,id',
            'email' => 'required|email',
            'role' => 'required|in:admin,member',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $team = Team::findOrFail($request->team_id);
        
        // Check if user has permission to invite members
        $isTeamAdmin = TeamMember::where('team_id', $request->team_id)
            ->where('user_id', auth()->id())
            ->where('role', 'admin')
            ->exists();
            
        if (!$isTeamAdmin) {
            return response()->json(['error' => 'You do not have permission to invite members to this team'], 403);
        }

        // Check if the user is already a member
        $existingUser = User::where('email', $request->email)->first();
        
        if ($existingUser) {
            $existingMember = TeamMember::where('team_id', $request->team_id)
                ->where('user_id', $existingUser->id)
                ->first();
                
            if ($existingMember) {
                return response()->json(['error' => 'This user is already a member of the team'], 422);
            }
        }

        // Check if there's already a pending invitation
        $existingInvitation = TeamInvitation::where('team_id', $request->team_id)
            ->where('email', $request->email)
            ->first();
            
        if ($existingInvitation) {
            return response()->json(['error' => 'There is already a pending invitation for this email'], 422);
        }

        // Create the invitation
        $invitation = TeamInvitation::create([
            'team_id' => $request->team_id,
            'email' => $request->email,
            'role' => $request->role,
            'token' => Str::random(40),
        ]);

        // TODO: Send invitation email
        // This would be implemented based on your email sending setup

        return response()->json(['message' => 'Invitation sent successfully', 'invitation' => $invitation], 201);
    }

    /**
     * Accept a team invitation.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function acceptInvitation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $invitation = TeamInvitation::where('token', $request->token)->first();
        
        if (!$invitation) {
            return response()->json(['error' => 'Invalid invitation token'], 404);
        }

        $team = Team::findOrFail($invitation->team_id);
        
        // Check if the authenticated user's email matches the invitation
        if (auth()->user()->email !== $invitation->email) {
            return response()->json(['error' => 'This invitation is not for your email address'], 403);
        }

        // Get the environment user
        $environmentUser = EnvironmentUser::where('user_id', auth()->id())
            ->where('environment_id', $team->environment_id)
            ->first();
            
        if (!$environmentUser) {
            // Create environment user if it doesn't exist
            $environmentUser = EnvironmentUser::create([
                'user_id' => auth()->id(),
                'environment_id' => $team->environment_id,
            ]);
        }

        // Create team member
        TeamMember::create([
            'team_id' => $invitation->team_id,
            'user_id' => auth()->id(),
            'environment_id' => $team->environment_id,
            'environment_user_id' => $environmentUser->id,
            'role' => $invitation->role,
            'joined_at' => now(),
        ]);

        // Delete the invitation
        $invitation->delete();

        return response()->json(['message' => 'You have joined the team successfully']);
    }

    /**
     * Remove a member from a team.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|exists:teams,id',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if user has permission to remove members
        $isTeamAdmin = TeamMember::where('team_id', $request->team_id)
            ->where('user_id', auth()->id())
            ->where('role', 'admin')
            ->exists();
            
        if (!$isTeamAdmin && auth()->id() != $request->user_id) {
            return response()->json(['error' => 'You do not have permission to remove members from this team'], 403);
        }

        // Find the member to remove
        $member = TeamMember::where('team_id', $request->team_id)
            ->where('user_id', $request->user_id)
            ->first();
            
        if (!$member) {
            return response()->json(['error' => 'This user is not a member of the team'], 404);
        }

        // Delete the member
        $member->delete();

        return response()->json(['message' => 'Member removed successfully']);
    }

    /**
     * Update a member's role.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMemberRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|exists:teams,id',
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:admin,member',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if user has permission to update roles
        $isTeamAdmin = TeamMember::where('team_id', $request->team_id)
            ->where('user_id', auth()->id())
            ->where('role', 'admin')
            ->exists();
            
        if (!$isTeamAdmin) {
            return response()->json(['error' => 'You do not have permission to update member roles'], 403);
        }

        // Find the member to update
        $member = TeamMember::where('team_id', $request->team_id)
            ->where('user_id', $request->user_id)
            ->first();
            
        if (!$member) {
            return response()->json(['error' => 'This user is not a member of the team'], 404);
        }

        // Update the role
        $member->update(['role' => $request->role]);

        return response()->json(['message' => 'Member role updated successfully', 'member' => $member]);
    }
}

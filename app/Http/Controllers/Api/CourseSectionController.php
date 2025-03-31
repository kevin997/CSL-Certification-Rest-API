<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="CourseSection",
 *     required={"course_id", "title", "order"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="course_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="Introduction to CSL Standards"),
 *     @OA\Property(property="description", type="string", example="Overview of CSL certification standards", nullable=true),
 *     @OA\Property(property="order", type="integer", example=1),
 *     @OA\Property(property="is_visible", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )

/**
 * @OA\Get(
 *     path="/api/courses/{courseId}/sections",
 *     summary="Get all sections for a course",
 *     description="Returns a list of all sections for a specific course",
 *     operationId="getCourseSections",
 *     tags={"Course Sections"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="courseId",
 *         in="path",
 *         description="Course ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/CourseSection")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Course not found"
 *     )
 * )

/**
 * @OA\Post(
 *     path="/api/courses/{courseId}/sections",
 *     summary="Create a new course section",
 *     description="Creates a new section for a specific course",
 *     operationId="createCourseSection",
 *     tags={"Course Sections"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="courseId",
 *         in="path",
 *         description="Course ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"title"},
 *             @OA\Property(property="title", type="string", example="Introduction to CSL Standards"),
 *             @OA\Property(property="description", type="string", example="Overview of CSL certification standards"),
 *             @OA\Property(property="order", type="integer", example=1),
 *             @OA\Property(property="is_visible", type="boolean", example=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Section created successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Section created successfully"),
 *             @OA\Property(property="data", ref="#/components/schemas/CourseSection")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Forbidden"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Course not found"
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error"
 *     )
 * )

/**
 * @OA\Get(
 *     path="/api/courses/{courseId}/sections/{id}",
 *     summary="Get a specific course section",
 *     description="Returns details of a specific section in a course",
 *     operationId="getCourseSection",
 *     tags={"Course Sections"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="courseId",
 *         in="path",
 *         description="Course ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Section ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="data", ref="#/components/schemas/CourseSection")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Course or section not found"
 *     )
 * )

/**
 * @OA\Put(
 *     path="/api/courses/{courseId}/sections/{id}",
 *     summary="Update a course section",
 *     description="Updates an existing section in a course",
 *     operationId="updateCourseSection",
 *     tags={"Course Sections"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="courseId",
 *         in="path",
 *         description="Course ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Section ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="title", type="string", example="Updated Section Title"),
 *             @OA\Property(property="description", type="string", example="Updated section description"),
 *             @OA\Property(property="order", type="integer", example=2),
 *             @OA\Property(property="is_visible", type="boolean", example=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Section updated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Section updated successfully"),
 *             @OA\Property(property="data", ref="#/components/schemas/CourseSection")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Forbidden"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Course or section not found"
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error"
 *     )
 * )

/**
 * @OA\Delete(
 *     path="/api/courses/{courseId}/sections/{id}",
 *     summary="Delete a course section",
 *     description="Deletes an existing section from a course",
 *     operationId="deleteCourseSection",
 *     tags={"Course Sections"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="courseId",
 *         in="path",
 *         description="Course ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Section ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Section deleted successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Section deleted successfully")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Forbidden"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Course or section not found"
 *     )
 * )

/**
 * @OA\Post(
 *     path="/api/courses/{courseId}/sections/reorder",
 *     summary="Reorder course sections",
 *     description="Updates the order of sections in a course",
 *     operationId="reorderCourseSections",
 *     tags={"Course Sections"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="courseId",
 *         in="path",
 *         description="Course ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"sections"},
 *             @OA\Property(
 *                 property="sections",
 *                 type="array",
 *                 description="Array of section IDs in the desired order",
 *                 @OA\Items(
 *                     type="integer",
 *                     example=1
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Sections reordered successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Sections reordered successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/CourseSection")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Forbidden"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Course not found"
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error"
 *     )
 * )

/**
 * @OA\Post(
 *     path="/api/courses/{courseId}/sections/{id}/toggle-visibility",
 *     summary="Toggle section visibility",
 *     description="Toggles the visibility of a course section",
 *     operationId="toggleSectionVisibility",
 *     tags={"Course Sections"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="courseId",
 *         in="path",
 *         description="Course ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Section ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Section visibility toggled successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Section visibility updated successfully"),
 *             @OA\Property(property="data", ref="#/components/schemas/CourseSection")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Forbidden"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Course or section not found"
 *     )
 * )
 */

class CourseSectionController extends Controller
{
    /**
     * Display a listing of the course sections.
     *
     * @param  int  $courseId
     * @return \Illuminate\Http\Response
     */
    public function index($courseId)
    {
        $course = Course::findOrFail($courseId);
        
        // Check if user has access to this course
        if ($course->status !== 'published' && $course->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view sections for this course',
            ], Response::HTTP_FORBIDDEN);
        }

        $sections = $course->sections()->orderBy('order')->get();

        return response()->json([
            'status' => 'success',
            'data' => $sections,
        ]);
    }

    /**
     * Store a newly created course section in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $courseId
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        
        // Check if user has permission to add sections to this course
        if ($course->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to add sections to this course',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
            'is_visible' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Determine the order if not provided
        if (!$request->has('order') || is_null($request->order)) {
            $maxOrder = $course->sections()->max('order') ?? -1;
            $order = $maxOrder + 1;
        } else {
            $order = $request->order;
            
            // Reorder existing sections if needed
            $this->reorderSectionsAfterInsertion($course, $order);
        }

        // Create section
        $section = new CourseSection();
        $section->course_id = $courseId;
        $section->title = $request->title;
        $section->description = $request->description;
        $section->order = $order;
        $section->is_visible = $request->is_visible ?? true;
        $section->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Course section created successfully',
            'data' => $section,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified course section.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $section = CourseSection::with('activities')->findOrFail($id);
        $course = Course::findOrFail($section->course_id);
        
        // Check if user has access to this course
        if ($course->status !== 'published' && $course->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this section',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'status' => 'success',
            'data' => $section,
        ]);
    }

    /**
     * Update the specified course section in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $section = CourseSection::findOrFail($id);
        $course = Course::findOrFail($section->course_id);
        
        // Check if user has permission to update this section
        if ($course->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this section',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
            'is_visible' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Handle order change if provided
        if ($request->has('order') && $request->order !== $section->order) {
            $this->reorderSectionsAfterUpdate($course, $section->order, $request->order);
            $section->order = $request->order;
        }

        // Update section fields
        if ($request->has('title')) $section->title = $request->title;
        if ($request->has('description')) $section->description = $request->description;
        if ($request->has('is_visible')) $section->is_visible = $request->is_visible;
        $section->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Course section updated successfully',
            'data' => $section,
        ]);
    }

    /**
     * Remove the specified course section from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $section = CourseSection::findOrFail($id);
        $course = Course::findOrFail($section->course_id);
        
        // Check if user has permission to delete this section
        if ($course->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this section',
            ], Response::HTTP_FORBIDDEN);
        }

        // Delete section
        $section->delete();
        
        // Reorder remaining sections
        $this->reorderSectionsAfterDeletion($course, $section->order);

        return response()->json([
            'status' => 'success',
            'message' => 'Course section deleted successfully',
        ]);
    }

    /**
     * Reorder course sections.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $courseId
     * @return \Illuminate\Http\Response
     */
    public function reorder(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        
        // Check if user has permission to reorder sections
        if ($course->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to reorder sections for this course',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'sections' => 'required|array',
            'sections.*.id' => 'required|integer|exists:course_sections,id',
            'sections.*.order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validate that all sections belong to the course
        foreach ($request->sections as $sectionData) {
            $section = CourseSection::findOrFail($sectionData['id']);
            if ($section->course_id != $courseId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'One or more sections do not belong to this course',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Update section orders
        foreach ($request->sections as $sectionData) {
            CourseSection::where('id', $sectionData['id'])
                ->update(['order' => $sectionData['order']]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Course sections reordered successfully',
        ]);
    }

    /**
     * Reorder sections after inserting a new section at a specific order.
     *
     * @param  \App\Models\Course  $course
     * @param  int  $insertOrder
     * @return void
     */
    private function reorderSectionsAfterInsertion($course, $insertOrder)
    {
        // Shift sections with order >= insertOrder one position up
        $course->sections()
            ->where('order', '>=', $insertOrder)
            ->increment('order');
    }

    /**
     * Reorder sections after updating a section's order.
     *
     * @param  \App\Models\Course  $course
     * @param  int  $oldOrder
     * @param  int  $newOrder
     * @return void
     */
    private function reorderSectionsAfterUpdate($course, $oldOrder, $newOrder)
    {
        if ($oldOrder < $newOrder) {
            // Moving down: decrement sections with order between old and new
            $course->sections()
                ->where('order', '>', $oldOrder)
                ->where('order', '<=', $newOrder)
                ->decrement('order');
        } else if ($oldOrder > $newOrder) {
            // Moving up: increment sections with order between new and old
            $course->sections()
                ->where('order', '>=', $newOrder)
                ->where('order', '<', $oldOrder)
                ->increment('order');
        }
    }

    /**
     * Reorder sections after deleting a section.
     *
     * @param  \App\Models\Course  $course
     * @param  int  $deletedOrder
     * @return void
     */
    private function reorderSectionsAfterDeletion($course, $deletedOrder)
    {
        // Shift sections with order > deletedOrder one position down
        $course->sections()
            ->where('order', '>', $deletedOrder)
            ->decrement('order');
    }
}

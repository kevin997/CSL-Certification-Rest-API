<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Course;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="Course",
 *     required={"title", "template_id", "status"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="Introduction to CSL Certification"),
 *     @OA\Property(property="course_code", type="string", example="CSL-INTRO-101", nullable=true),
 *     @OA\Property(property="slug", type="string", example="introduction-to-csl-certification"),
 *     @OA\Property(property="description", type="string", example="A comprehensive introduction to CSL certification standards", nullable=true),
 *     @OA\Property(property="template_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="status", type="string", enum={"draft", "published", "archived"}, example="published"),
 *     @OA\Property(property="featured_image", type="string", example="courses/featured/intro-csl.jpg", nullable=true),
 *     @OA\Property(property="start_date", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="end_date", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="enrollment_limit", type="integer", example=100, nullable=true),
 *     @OA\Property(property="is_featured", type="boolean", example=false),
 *     @OA\Property(property="created_by", type="integer", format="int64", example=1),
 *     @OA\Property(property="published_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )

/**
 * @OA\Get(
 *     path="/api/courses",
 *     summary="Get all courses",
 *     description="Returns a list of all courses with optional filtering",
 *     operationId="getCourses",
 *     tags={"Courses"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         description="Search term for filtering courses by title or description",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         description="Filter courses by status",
 *         required=false,
 *         @OA\Schema(type="string", enum={"draft", "published", "archived"})
 *     ),
 *     @OA\Parameter(
 *         name="category_id",
 *         in="query",
 *         description="Filter courses by category ID",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="created_by",
 *         in="query",
 *         description="Filter courses by creator user ID",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="sort_by",
 *         in="query",
 *         description="Field to sort by",
 *         required=false,
 *         @OA\Schema(type="string", enum={"title", "created_at", "published_at", "start_date"})
 *     ),
 *     @OA\Parameter(
 *         name="sort_order",
 *         in="query",
 *         description="Sort order (asc or desc)",
 *         required=false,
 *         @OA\Schema(type="string", enum={"asc", "desc"})
 *     ),
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Number of courses per page",
 *         required=false,
 *         @OA\Schema(type="integer", minimum=1, maximum=100)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="current_page", type="integer", example=1),
 *                 @OA\Property(
 *                     property="data",
 *                     type="array",
 *                     @OA\Items(ref="#/components/schemas/Course")
 *                 ),
 *                 @OA\Property(property="first_page_url", type="string", example="http://example.com/api/courses?page=1"),
 *                 @OA\Property(property="from", type="integer", example=1),
 *                 @OA\Property(property="last_page", type="integer", example=5),
 *                 @OA\Property(property="last_page_url", type="string", example="http://example.com/api/courses?page=5"),
 *                 @OA\Property(property="next_page_url", type="string", example="http://example.com/api/courses?page=2"),
 *                 @OA\Property(property="path", type="string", example="http://example.com/api/courses"),
 *                 @OA\Property(property="per_page", type="integer", example=15),
 *                 @OA\Property(property="prev_page_url", type="string", example=null),
 *                 @OA\Property(property="to", type="integer", example=15),
 *                 @OA\Property(property="total", type="integer", example=75)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/courses",
 *     summary="Create a new course",
 *     description="Creates a new course from a template",
 *     operationId="createCourse",
 *     tags={"Courses"},
 *     security={{"sanctum":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"template_id", "title", "description", "status"},
 *             @OA\Property(property="template_id", type="integer", example=1),
 *             @OA\Property(property="title", type="string", example="Introduction to CSL Certification"),
 *             @OA\Property(property="slug", type="string", example="introduction-to-csl-certification"),
 *             @OA\Property(property="description", type="string", example="A comprehensive introduction to CSL certification standards"),
 *             @OA\Property(property="short_description", type="string", example="Learn the basics of CSL certification"),
 *             @OA\Property(property="status", type="string", enum={"draft", "published", "archived"}, example="draft"),
 *             @OA\Property(property="category_id", type="integer", example=1),
 *             @OA\Property(property="featured_image", type="string", example="courses/featured/intro-csl.jpg"),
 *             @OA\Property(property="start_date", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *             @OA\Property(property="end_date", type="string", format="date-time", example="2023-12-31T23:59:59Z"),
 *             @OA\Property(property="enrollment_limit", type="integer", example=100),
 *             @OA\Property(property="price", type="number", format="float", example=99.99),
 *             @OA\Property(property="currency", type="string", example="USD"),
 *             @OA\Property(property="is_featured", type="boolean", example=false),
 *             @OA\Property(property="meta_title", type="string", example="Learn CSL Certification | CSL Platform"),
 *             @OA\Property(property="meta_description", type="string", example="Comprehensive course on CSL certification standards"),
 *             @OA\Property(property="meta_keywords", type="string", example="CSL, certification, learning")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Course created successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Course created successfully"),
 *             @OA\Property(property="data", ref="#/components/schemas/Course")
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
 *         response=422,
 *         description="Validation error"
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/courses/{id}",
 *     summary="Get a specific course",
 *     description="Returns details of a specific course by ID or slug",
 *     operationId="getCourse",
 *     tags={"Courses"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Course ID or slug",
 *         required=true,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="data", ref="#/components/schemas/Course")
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
 *
 * @OA\Put(
 *     path="/api/courses/{id}",
 *     summary="Update a course",
 *     description="Updates an existing course",
 *     operationId="updateCourse",
 *     tags={"Courses"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Course ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="title", type="string", example="Updated CSL Certification Course"),
 *             @OA\Property(property="slug", type="string", example="updated-csl-certification-course"),
 *             @OA\Property(property="description", type="string", example="Updated description for the CSL certification course"),
 *             @OA\Property(property="short_description", type="string", example="Updated short description"),
 *             @OA\Property(property="status", type="string", enum={"draft", "published", "archived"}, example="published"),
 *             @OA\Property(property="category_id", type="integer", example=2),
 *             @OA\Property(property="featured_image", type="string", example="courses/featured/updated-csl.jpg"),
 *             @OA\Property(property="start_date", type="string", format="date-time", example="2023-02-01T00:00:00Z"),
 *             @OA\Property(property="end_date", type="string", format="date-time", example="2023-12-31T23:59:59Z"),
 *             @OA\Property(property="enrollment_limit", type="integer", example=150),
 *             @OA\Property(property="price", type="number", format="float", example=129.99),
 *             @OA\Property(property="currency", type="string", example="USD"),
 *             @OA\Property(property="is_featured", type="boolean", example=true),
 *             @OA\Property(property="meta_title", type="string", example="Updated Meta Title"),
 *             @OA\Property(property="meta_description", type="string", example="Updated Meta Description"),
 *             @OA\Property(property="meta_keywords", type="string", example="updated, keywords")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Course updated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Course updated successfully"),
 *             @OA\Property(property="data", ref="#/components/schemas/Course")
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
 *
 * @OA\Delete(
 *     path="/api/courses/{id}",
 *     summary="Delete a course",
 *     description="Deletes an existing course",
 *     operationId="deleteCourse",
 *     tags={"Courses"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Course ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Course deleted successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Course deleted successfully")
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
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/courses/{id}/publish",
 *     summary="Publish a course",
 *     description="Changes the status of a course to published",
 *     operationId="publishCourse",
 *     tags={"Courses"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Course ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Course published successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Course published successfully"),
 *             @OA\Property(property="data", ref="#/components/schemas/Course")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Course is already published"
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
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/courses/{id}/archive",
 *     summary="Archive a course",
 *     description="Changes the status of a course to archived",
 *     operationId="archiveCourse",
 *     tags={"Courses"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Course ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Course archived successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Course archived successfully"),
 *             @OA\Property(property="data", ref="#/components/schemas/Course")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Course is already archived"
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
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/courses/{id}/duplicate",
 *     summary="Duplicate a course",
 *     description="Creates a copy of an existing course",
 *     operationId="duplicateCourse",
 *     tags={"Courses"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Course ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Course duplicated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Course duplicated successfully"),
 *             @OA\Property(property="data", ref="#/components/schemas/Course")
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
 *     )
 * )
 */

class CourseController extends Controller
{
    /**
     * Display a listing of the courses.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,published,archived',
            'category_id' => 'nullable|integer|exists:categories,id',
            'created_by' => 'nullable|integer|exists:users,id',
            'sort_by' => 'nullable|string|in:title,created_at,published_at,start_date',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = Course::query();

        // Apply search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Apply category filter
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Apply creator filter
        if ($request->has('created_by')) {
            $query->where('created_by', $request->input('created_by'));
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Apply pagination
        $perPage = $request->input('per_page', 15);
        $courses = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $courses,
        ]);
    }

    /**
     * Store a newly created course in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'course_code' => 'nullable|string|max:50',
            'description' => 'required|string',
            'template_id' => 'required|integer|exists:templates,id',
            'environment_id' => 'nullable|integer|exists:environments,id',
            'status' => 'required|string|in:draft,published,archived',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_self_paced' => 'nullable|boolean',
            'estimated_duration' => 'nullable|integer|min:1',
            'difficulty_level' => 'nullable|string|in:beginner,intermediate,advanced',
            'thumbnail_url' => 'nullable|string|url',
            'featured_image' => 'nullable|string|url',
            'is_featured' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'published_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if user has access to the template
        $template = Template::find($request->template_id);
        if (!$template || (!$template->is_public && $template->created_by !== Auth::id())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template not found or you do not have permission to use this template',
            ], Response::HTTP_FORBIDDEN);
        }

        // Generate slug if not provided
        if (!$request->has('slug') || empty($request->slug)) {
            $slug = Str::slug($request->title);
            $originalSlug = $slug;
            $count = 1;

            // Ensure slug is unique
            while (Course::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $count;
                $count++;
            }
        } else {
            $slug = $request->slug;
        }

        // Create course
        $course = new Course();
        $course->title = $request->title;
        $course->slug = $slug; // Set the generated slug
        $course->description = $request->description;
        $course->template_id = $request->template_id;
        $course->environment_id = $request->environment_id;
        $course->status = $request->status;
        $course->start_date = $request->start_date;
        $course->end_date = $request->end_date;
        $course->is_self_paced = $request->is_self_paced ?? false;
        $course->estimated_duration = $request->estimated_duration;
        $course->difficulty_level = $request->difficulty_level ?? 'beginner';
        
        // Store image URLs
        $course->thumbnail_url = $request->thumbnail_url;
        $course->featured_image = $request->featured_image;
        
        // Store meta information
        $course->is_featured = $request->is_featured ?? false;
        $course->meta_title = $request->meta_title;
        $course->meta_description = $request->meta_description;
        $course->meta_keywords = $request->meta_keywords;
        
        $course->published_at = $request->status === 'published' ? now() : $request->published_at;
        $course->created_by = Auth::id();
        $course->save();

        // Create course sections based on template blocks
        $this->createCourseSectionsFromTemplate($course, $template);

        return response()->json([
            'status' => 'success',
            'message' => 'Course created successfully',
            'data' => $course,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified course.
     *
     * @param  string  $identifier
     * @return \Illuminate\Http\Response
     */
    public function show($identifier)
    {
        // Check if identifier is numeric (ID) or string (slug)
        $course = is_numeric($identifier) 
            ? Course::findOrFail($identifier)
            : Course::where('slug', $identifier)->firstOrFail();

        // Load relationships
        $course->load(['template', 'creator', 'sections.activities']);

        return response()->json([
            'status' => 'success',
            'data' => $course,
        ]);
    }

    /**
     * Update the specified course in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        // Check if user has permission to update this course
        if ($course->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this course',
            ], Response::HTTP_FORBIDDEN);
        }
        
        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'course_code' => 'nullable|string|max:50',
            'description' => 'string',
            'template_id' => 'integer|exists:templates,id',
            'environment_id' => 'nullable|integer|exists:environments,id',
            'status' => 'string|in:draft,published,archived',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_self_paced' => 'nullable|boolean',
            'estimated_duration' => 'nullable|integer|min:1',
            'difficulty_level' => 'nullable|string|in:beginner,intermediate,advanced',
            'thumbnail_url' => 'nullable|string',
            'featured_image' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'published_at' => 'nullable|date',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        // Generate slug if it doesn't exist or if title is being updated
        if (empty($course->slug) || ($request->has('title') && $request->title != $course->title)) {
            $slug = Str::slug($request->title ?? $course->title);
            $originalSlug = $slug;
            $count = 1;

            // Ensure slug is unique
            while (Course::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $originalSlug . '-' . $count;
                $count++;
            }
            $course->slug = $slug;
        } else if ($request->has('slug')) {
            // If slug is explicitly provided in the request
            $course->slug = $request->slug;
        }
        
        // Update published_at if status changes to published
        if ($request->has('status') && $request->status === 'published' && $course->status !== 'published') {
            $course->published_at = now();
        }

        // Update course fields
        if ($request->has('title')) $course->title = $request->title;
        if ($request->has('description')) $course->description = $request->description;
        if ($request->has('template_id')) $course->template_id = $request->template_id;
        if ($request->has('environment_id')) $course->environment_id = $request->environment_id;
        if ($request->has('status')) $course->status = $request->status;
        if ($request->has('start_date')) $course->start_date = $request->start_date;
        if ($request->has('end_date')) $course->end_date = $request->end_date;
        if ($request->has('enrollment_limit')) $course->enrollment_limit = $request->enrollment_limit;
        if ($request->has('is_self_paced')) $course->is_self_paced = $request->is_self_paced;
        if ($request->has('estimated_duration')) $course->estimated_duration = $request->estimated_duration;
        if ($request->has('difficulty_level')) $course->difficulty_level = $request->difficulty_level;
        if ($request->has('thumbnail_url')) $course->thumbnail_url = $request->thumbnail_url;
        if ($request->has('featured_image')) $course->featured_image = $request->featured_image;
        if ($request->has('is_featured')) $course->is_featured = $request->is_featured;
        if ($request->has('meta_title')) $course->meta_title = $request->meta_title;
        if ($request->has('meta_description')) $course->meta_description = $request->meta_description;
        if ($request->has('meta_keywords')) $course->meta_keywords = $request->meta_keywords;
        if ($request->has('published_at')) $course->published_at = $request->published_at;

        $course->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Course updated successfully',
            'data' => $course,
        ]);
    }

    /**
     * Remove the specified course from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $course = Course::findOrFail($id);

        // Check if user has permission to delete this course
        if ($course->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this course',
            ], Response::HTTP_FORBIDDEN);
        }

        // Delete course
        $course->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Course deleted successfully',
        ]);
    }

    /**
     * Publish the specified course.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function publish($id)
    {
        $course = Course::findOrFail($id);

        // Check if user has permission to publish this course
        if ($course->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to publish this course',
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if course is already published
        if ($course->status === 'published') {
            return response()->json([
                'status' => 'error',
                'message' => 'Course is already published',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Publish course
        $course->status = 'published';
        $course->published_at = now();
        $course->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Course published successfully',
            'data' => $course,
        ]);
    }

    /**
     * Archive the specified course.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function archive($id)
    {
        $course = Course::findOrFail($id);

        // Check if user has permission to archive this course
        if ($course->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to archive this course',
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if course is already archived
        if ($course->status === 'archived') {
            return response()->json([
                'status' => 'error',
                'message' => 'Course is already archived',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Archive course
        $course->status = 'archived';
        $course->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Course archived successfully',
            'data' => $course,
        ]);
    }

    /**
     * Duplicate the specified course.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function duplicate($id)
    {
        $course = Course::findOrFail($id);

        // Check if user has permission to duplicate this course
        if ($course->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to duplicate this course',
            ], Response::HTTP_FORBIDDEN);
        }

        // Create a new course based on the existing one
        $newCourse = $course->replicate();
        $newCourse->title = 'Copy of ' . $course->title;
        
        // Generate a unique slug
        $slug = Str::slug($newCourse->title);
        $originalSlug = $slug;
        $count = 1;

        while (Course::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }
        
        $newCourse->slug = $slug;
        $newCourse->status = 'draft';
        $newCourse->published_at = null;
        $newCourse->created_at = now();
        $newCourse->updated_at = now();
        $newCourse->save();

        // Duplicate course sections and activities
        foreach ($course->sections as $section) {
            $newSection = $section->replicate();
            $newSection->course_id = $newCourse->id;
            $newSection->save();

            foreach ($section->activities as $activity) {
                $newActivity = $activity->replicate();
                $newActivity->course_section_id = $newSection->id;
                $newActivity->save();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Course duplicated successfully',
            'data' => $newCourse,
        ]);
    }

    /**
     * Create course sections from template blocks.
     *
     * @param  \App\Models\Course  $course
     * @param  \App\Models\Template|\Illuminate\Database\Eloquent\Collection  $template
     * @return void
     */
    private function createCourseSectionsFromTemplate($course, $template)
    {
        // If $template is a collection, get the first item
        if ($template instanceof \Illuminate\Database\Eloquent\Collection) {
            $template = $template->first();
        }
        
        // If template is null, return early
        if (!$template) {
            return;
        }
        
        // Load template blocks with activities
        $template->load(['blocks.activities']);

        // Create course sections from template blocks
        foreach ($template->blocks as $block) {
            // Create course section
            $section = $course->sections()->create([
                'title' => $block->title,
                'description' => $block->description,
                'order' => $block->order,
            ]);

            // Create course activities from template activities
            foreach ($block->activities as $activity) {
                // Instead of creating a new Activity, let's directly use the existing activity
                // Associate the activity with the course section using the pivot table
                $section->items()->create([
                    'activity_id' => $activity->id,
                    'title' => $activity->title, // Optional override
                    'description' => $activity->description, // Optional override
                    'order' => $activity->order,
                    'is_published' => true,
                    'is_required' => $activity->is_required ?? true,
                    'created_by' => auth()->id(),
                ]);
            }
        }
    }
}

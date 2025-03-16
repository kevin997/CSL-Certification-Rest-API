<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @OA\Info(
 *     title="CSL Certification Platform API",
 *     version="1.0.0",
 *     description="API documentation for the CSL Certification Platform",
 *     @OA\Contact(
 *         email="support@csl-certification.com",
 *         name="CSL Support"
 *     ),
 *     @OA\License(
 *         name="Proprietary",
 *         url="https://csl-certification.com/license"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="/api",
 *     description="CSL Certification Platform API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * 
 * @OA\Schema(
 *     schema="User",
 *     required={"name", "email"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="role", type="string", enum={"admin", "instructor", "student"}, example="instructor"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="API endpoints for user authentication"
 * )
 * 
 * @OA\Tag(
 *     name="Templates",
 *     description="API endpoints for template management"
 * )
 * 
 * @OA\Tag(
 *     name="Blocks",
 *     description="API endpoints for block management"
 * )
 * 
 * @OA\Tag(
 *     name="Activities",
 *     description="API endpoints for activity management"
 * )
 * 
 * @OA\Tag(
 *     name="Content",
 *     description="API endpoints for content management"
 * )
 * 
 * @OA\Tag(
 *     name="Courses",
 *     description="API endpoints for course management"
 * )
 * 
 * @OA\Tag(
 *     name="Enrollments",
 *     description="API endpoints for enrollment management"
 * )
 * 
 * @OA\Tag(
 *     name="Activity Completions",
 *     description="API endpoints for activity completion tracking"
 * )
 * 
 * @OA\Tag(
 *     name="Products",
 *     description="API endpoints for product management"
 * )
 * 
 * @OA\Tag(
 *     name="Orders",
 *     description="API endpoints for order management"
 * )
 * 
 * @OA\Tag(
 *     name="Referrals",
 *     description="API endpoints for referral management"
 * )
 * 
 * @OA\Tag(
 *     name="Branding",
 *     description="API endpoints for branding management"
 * )
 */
class ApiDocController extends Controller
{
    // This controller doesn't need any methods as it's only used for Swagger annotations
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\Course;
use App\Models\FAQ;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class ReviewController extends Controller
{

    public function index(Request $request)
    {
        $successMessage = Session::get('success');
        $errorMessage = Session::get('error');

        // Get search query from the request
        $search = $request->input('search');

        // Modify the query to include search functionality
        $reviews = Review::with(['course', 'user'])
            ->when($search, function ($query, $search) {
                return $query->whereHas('course', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                })->orWhereHas('user', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(10);

        return view('admin.reviews.index', compact('reviews', 'successMessage', 'errorMessage', 'search'));
    }

    public function store(StoreReviewRequest $request, Course $course)
    {
        $user = Auth::user();

        if (!$user->hasRole('student')) {
            Session::flash('error', 'Only students can create reviews.');
            return redirect()->back();
        }

        // Check if the user has already reviewed this course (including soft deleted reviews)
        $existingReview = Review::withTrashed()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existingReview) {
            if ($existingReview->trashed()) {
                $existingReview->restore();
                Session::flash('success', 'Your previous review has been restored.');
                return redirect()->back();
            } else {
                Session::flash('error', 'You have already reviewed this course.');
                return redirect()->back();
            }
        }

        DB::transaction(function () use ($request, $course, $user) {
            $validated = $request->validated();
            $validated['course_id'] = $course->id;
            $validated['user_id'] = $user->id;

            Review::create($validated);
        });

        Session::flash('success', 'Thank you for your review.');

        // Fetch the video data for the view
        $currentVideoId = $request->input('courseVideoId');
        $video = $course->course_videos()->find($currentVideoId);
        $courseImage = $course->course_images()->orderByDesc('id')->get();
        $faqs = FAQ::orderByDesc('id')->take(4)->get();
        $allFaqs = FAQ::orderByDesc('id')->get();
        $reviews = Review::where('course_id', $course->id)->orderByDesc('created_at')->get();

        return view('front.learning', [
            'course' => $course,
            'currentVideoId' => $currentVideoId,
            'video' => $video,
            'courseImage' => $courseImage,
            'faqs' => $faqs,
            'allFaqs' => $allFaqs,
            'reviews' => $reviews
        ]);
    }

    public function edit(Review $review)
    {
        return view('admin.reviews.edit', compact('review'));
    }

    public function update(StoreReviewRequest $request, Review $review)
    {
        $user = Auth::user();

        if (!$user->hasRole('student')) {
            return redirect()->back()->with('error', 'Only students can update reviews.');
        }

        if ($review->user_id != $user->id) {
            return redirect()->back()->with('error', 'You can only update your own reviews.');
        }

        DB::transaction(function () use ($request, $review) {
            $validated = $request->validated();
            $review->update($validated);
        });
        Session::flash('success', 'Review updated successfully.');
        return redirect()->route('front.learning');
    }

    public function destroy(Review $review)
    {
        try {
            DB::transaction(function () use ($review) {
                $review->delete();
            });
            Session::flash('success', 'Review deleted successfully.');
            return redirect()->route('admin.reviews.index');
        } catch (\Exception $e) {
            Session::flash('error', $e->getMessage());
            return redirect()->route('admin.reviews.index');
        }
    }
}

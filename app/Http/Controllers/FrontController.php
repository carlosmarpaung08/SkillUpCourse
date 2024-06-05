<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscribeTransactionRequest;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseImage;
use App\Models\SubscribeTransaction;
use App\Models\Notification;
use App\Models\CourseVideo;
use App\Models\FAQ;
use App\Models\Review;
use App\Models\CourseVideoStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class FrontController extends Controller
{
    private function getUserNotifications()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();
        $unreadNotifications = $notifications->where('status', 'unread');

        return compact('notifications', 'unreadNotifications');
    }

    public function index()
    {
        $faqs = FAQ::orderByDesc('id')->take(4)->get();
        $allFaqs = FAQ::orderByDesc('id')->get();
        $categories = Category::orderByDesc('id')->get();
        $courses = Course::with(['category', 'teacher', 'students'])->orderByDesc('id')->get();
        $notificationsData = $this->getUserNotifications();
        $topCourses = Course::with('reviews')
            ->withCount('reviews')
            ->get()
            ->sortByDesc(function ($course) {
                return $course->averageRating();
            })
            ->take(10); // Misalnya, mengambil 10 kursus teratas

        return view('front.index', compact('courses', 'categories', 'faqs', 'allFaqs','topCourses') + $notificationsData);
    }

    public function loadMoreFaqs()
    {
        $faqs = FAQ::orderByDesc('id')->skip(4)->take(PHP_INT_MAX)->get();
        return response()->json($faqs);
    }

    public function details(Course $course)
    {
        $courseImage = $course->course_images()->orderByDesc('id')->get();
        $faqs = FAQ::orderByDesc('id')->take(4)->get();
        $allFaqs = FAQ::orderByDesc('id')->get();
        $notificationsData = $this->getUserNotifications();
        $reviews = Review::where('course_id', $course->id)->orderByDesc('created_at')->get();
        return view('front.details', compact('course', 'courseImage', 'faqs', 'allFaqs', 'reviews') + $notificationsData);
    }

    public function category(Category $category, Request $request)
    {
        $courseQuery = Course::query();
        $faqs = FAQ::orderByDesc('id')->take(4)->get();
        $allFaqs = FAQ::orderByDesc('id')->get();
        $notificationsData = $this->getUserNotifications();
        $search = $request->input('search');

        // Retrieve courses associated with the category using the query builder
        $query = $category->courses()->orderByDesc('id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%');
            });
        }

        // Paginate the results
        $courses = $query->paginate(4);
        $reviews = Review::select('id', 'user_id', 'course_id', 'rating', 'note', 'created_at')
            ->distinct('user_id')
            ->with('user')
            ->orderByDesc('created_at')
            ->get();
        $notificationsData = $this->getUserNotifications();

        return view('front.category', compact('category', 'courses', 'faqs', 'search', 'allFaqs', 'reviews') + $notificationsData);
    }

    public function pricing()
    {
        $faqs = FAQ::orderByDesc('id')->take(4)->get();
        $allFaqs = FAQ::orderByDesc('id')->get();
        $user = Auth::user();
        if (!$user) {
            // Redirect unauthenticated users to the login page
            return redirect()->route('login');
        }
        if ($user->hasActiveSubscription()) {
            return redirect()->route('front.index');
        }
        $reviews = Review::select('id', 'user_id', 'course_id', 'rating', 'note', 'created_at')
            ->distinct('user_id')
            ->with('user')
            ->orderByDesc('created_at')
            ->get();
        $notificationsData = $this->getUserNotifications();

        return view('front.pricing', compact('faqs', 'allFaqs', 'reviews') + $notificationsData);
    }

    public function checkout()
    {
        $user = Auth::user();
        if ($user->hasActiveSubscription()) {
            return redirect()->route('front.index');
        }
        $notificationsData = $this->getUserNotifications();

        return view('front.checkout', $notificationsData);
    }

    public function checkout_store(StoreSubscribeTransactionRequest $request, NotificationController $notificationController)
    {
        $user = Auth::user();
        if ($user->hasActiveSubscription()) {
            return redirect()->route('front.index');
        }

        DB::transaction(function () use ($request, $user, $notificationController) {
            $validated = $request->validated();

            if ($request->hasFile('proof')) {
                $proofPath = $request->file('proof')->store('proofs', 'public');
                $validated['proof'] = $proofPath;
            }

            $validated['user_id'] = $user->id;
            $validated['total_amount'] = 429000;
            $validated['is_paid'] = false;

            $transaction = SubscribeTransaction::create($validated);

            // Tambahkan notifikasi "Pembayaran diproses"
            $notificationController->createPaymentNotification($user);
        });

        return redirect()->route('dashboard');
    }

    public function learning(Course $course, $courseVideoId)
    {
        $user = Auth::user();
        if (!$user->hasActiveSubscription()) {
            return redirect()->route('front.pricing');
        }

        $courseImage = $course->course_images()->orderByDesc('id')->get();
        $faqs = FAQ::orderByDesc('id')->take(4)->get();
        $allFaqs = FAQ::orderByDesc('id')->get();

        $video = $course->course_videos()->firstWhere('id', $courseVideoId);
        $user->courses()->syncWithoutDetaching($course->id);

        $notificationsData = $this->getUserNotifications();

        // Ambil daftar ulasan untuk kursus
        $reviews = Review::where('course_id', $course->id)->orderByDesc('created_at')->get();

        // Ambil status video untuk pengguna yang sedang masuk
        $videoStatuses = CourseVideoStatus::where('user_id', $user->id)->pluck('watched', 'course_video_id')->toArray();

        return view('front.learning', compact('course', 'video', 'courseImage', 'faqs', 'allFaqs', 'reviews', 'videoStatuses') + $notificationsData);
    }

    public function markVideoAsWatched($videoId)
    {
        $user = Auth::user();
        $video = CourseVideo::find($videoId);

        if ($video) {
            // Cari atau buat status video berdasarkan course_video_id dan user_id
            $status = CourseVideoStatus::firstOrCreate(
                ['course_video_id' => $video->id, 'user_id' => $user->id],
                ['watched' => true]
            );

            $status->watched = true;
            $status->save();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 404);
    }

    public function checkoutDetails()
    {
        $transactions = SubscribeTransaction::where('user_id', Auth::id())->get();
        $notificationsData = $this->getUserNotifications();

        return view('front.checkout_details', compact('transactions') + $notificationsData);
    }

    public function checkoutViewDetails()
    {
        $transactions = SubscribeTransaction::where('user_id', Auth::id())->latest()->first();
        $notificationsData = $this->getUserNotifications();

        return view('front.checkout_view_details', compact('transactions') + $notificationsData);
    }

    public function exportPdf(SubscribeTransaction $transaction)
    {
        $data = [
            'transactions' => $transaction,
        ];

        $pdf = PDF::loadView('front.checkout_details_pdf', $data);
        return $pdf->download('checkout_details.pdf');
    }
}

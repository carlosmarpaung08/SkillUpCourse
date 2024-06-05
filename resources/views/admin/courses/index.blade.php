<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center bg-indigo-100 px-4 py-3 rounded-md shadow-md">
            <h2 class="text-2xl font-semibold text-indigo-900 leading-tight">Manage Courses</h2>
            <a href="{{ route('admin.courses.create') }}"
                class="px-4 py-2 bg-indigo-700 hover:bg-indigo-600 text-white rounded-md transition-colors duration-300 flex items-center space-x-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                <span>Add New</span>
            </a>
        </div>
    </x-slot>

    <!-- Flash messages -->
    @include('layouts.backend.session-message')

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8">
                <div class="flex justify-end mb-8">
                    <form action="{{ route('admin.courses.index') }}" method="GET" class="flex items-center">
                        <input type="text" name="search" placeholder="Search courses"
                            class="border border-gray-300 rounded-l-md px-4 py-2 focus:outline-none focus:ring focus:border-blue-300 text-sm">
                        <button type="submit"
                            class="bg-blue-500 text-white px-4 py-2 rounded-r-md hover:bg-blue-600 focus:outline-none focus:bg-blue-600 transition-colors duration-300 text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M15.293 12.707a1 1 0 01-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 111.414 1.414L13.414 9H18a1 1 0 010 2h-4.586l1.879 1.879a1 1 0 11-1.414 1.414zM10 18a8 8 0 100-16 8 8 0 000 16z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>
                    </form>
                </div>
                @forelse ($courses as $course)
                    <div
                        class="bg-gray-100 rounded-lg shadow-md p-6 flex flex-col md:flex-row gap-5 justify-between items-center">
                        <!-- Course thumbnail and information -->
                        <div class="flex flex-row items-center gap-5 md:gap-8">
                            <img src="{{ Storage::url($course->thumbnail) }}" alt="{{ $course->name }}"
                                class="rounded-lg object-cover w-32 h-24">
                            <div class="flex flex-col">
                                <h3 class="text-xl font-semibold text-gray-800">{{ $course->name }}</h3>
                                <p class="text-gray-600">{{ $course->category->name }}</p>
                            </div>
                        </div>
                        <!-- Course details -->
                        <div class="flex flex-col md:flex-row items-center md:gap-8">
                            <div class="flex flex-col items-center md:text-left">
                                <p class="text-gray-600">Students</p>
                                <h3 class="text-xl font-semibold text-gray-800">{{ $course->students->count() }}</h3>
                            </div>
                            <div class="flex flex-col items-center md:text-left">
                                <p class="text-gray-600">Videos</p>
                                <h3 class="text-xl font-semibold text-gray-800">{{ $course->course_videos->count() }}
                                </h3>
                            </div>
                            <div class="flex flex-col items-center md:text-left">
                                <p class="text-gray-600">Teacher</p>
                                <h3 class="text-xl font-semibold text-gray-800">
                                    {{ $course->teacher ? $course->teacher->user->name : 'Unknown Teacher' }}</h3>
                            </div>
                        </div>
                        <div class="flex flex-col md:flex-row items-center md:gap-8">
                            <a href="{{ route('admin.courses.show', $course) }}"
                                class="flex items-center px-6 py-3 bg-indigo-700 text-white rounded-full">
                                <i class="fas fa-cogs mr-1"></i>
                                <span>Manage</span>
                            </a>
                            <form action="{{ route('admin.courses.destroy', $course) }}" method="POST"
                                class="delete-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit" id="delete-btn"
                                    class="flex items-center justify-center px-4 py-2 bg-red-700 text-white rounded-md">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M21.0697 5.23C19.4597 5.07 17.8497 4.95 16.2297 4.86V4.85L16.0097 3.55C15.8597 2.63 15.6397 1.25 13.2997 1.25H10.6797C8.34967 1.25 8.12967 2.57 7.96967 3.54L7.75967 4.82C6.82967 4.88 5.89967 4.94 4.96967 5.03L2.92967 5.23C2.50967 5.27 2.20967 5.64 2.24967 6.05C2.28967 6.46 2.64967 6.76 3.06967 6.72L5.10967 6.52C10.3497 6 15.6297 6.2 20.9297 6.73C20.9597 6.73 20.9797 6.73 21.0097 6.73C21.3897 6.73 21.7197 6.44 21.7597 6.05C21.7897 5.64 21.4897 5.27 21.0697 5.23Z"
                                            fill="white" />
                                        <path
                                            d="M19.2297 8.14C18.9897 7.89 18.6597 7.75 18.3197 7.75H5.67975C5.33975 7.75 4.99975 7.89 4.76975 8.14C4.53975 8.39 4.40975 8.73 4.42975 9.08L5.04975 19.34C5.15975 20.86 5.29975 22.76 8.78975 22.76H15.2097C18.6997 22.76 18.8397 20.87 18.9497 19.34L19.5697 9.09C19.5897 8.73 19.4597 8.39 19.2297 8.14ZM13.6597 17.75H10.3297C9.91975 17.75 9.57975 17.41 9.57975 17C9.57975 16.59 9.91975 16.25 10.3297 16.25H13.6597C14.0697 16.25 14.4097 16.59 14.4097 17C14.4097 17.41 14.0697 17.75 13.6597 17.75ZM14.4997 13.75H9.49975C9.08975 13.75 8.74975 13.41 8.74975 13C8.74975 12.59 9.08975 12.25 9.49975 12.25H14.4997C14.9097 12.25 15.2497 12.59 15.2497 13C15.2497 13.41 14.9097 13.75 14.4997 13.75Z"
                                            fill="white" />
                                    </svg>
                                    <span class="ml-2">Delete</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500">There are no courses available.</p>
                @endforelse
            </div>

            <!-- Pagination -->
            <div class="mt-4 flex justify-center">
                {{ $courses->appends(['search' => request('search')])->links('vendors.pagination.custom') }}
            </div>
        </div>
    </div>
</x-app-layout>

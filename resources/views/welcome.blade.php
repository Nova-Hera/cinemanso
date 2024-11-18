@extends('layout.site')
@section('title','Home')
@section('content')
                <main>
                    <section class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($movies as $movie)
                        <a href="{{ route('movie.show', $movie->id) }}">
                            <div class="relative aspect-video">
                                <img src="{{ $movie->image }}" alt="{{ $movie->title }}" class="object-cover w-full h-full rounded-lg shadow-[0px 14px 34px 0px rgba(0,0,0,0.08)]">
                                <p class="absolute bottom-0 left-0 p-2 text-white bg-black bg-opacity-50">{{ $movie->title }}</p>
                            </div>
                        </a>
                        @endforeach
                    </section>
                </main>
@endsection
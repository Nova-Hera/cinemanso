@extends('layout.site')
@section('title',"Filme: $movie->title")
@section('content')
    <main>
        <section class="movie">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <img src="{{ asset($movie->image) }}" alt="{{ $movie->title }}" class="img-fluid rounded-lg shadow-[0px 14px 34px 0px rgba(0,0,0,0.08)]">
                    </div>
                    <div class="col-md-6">
                        <h2>{{ $movie->title }}</h2>
                        <p><strong>Data de Lançamento:</strong> {{ $movie->release_date }}</p>
                        <p><strong>Classificação Média:</strong> {{ $movie->average_rating }}</p>
                        <p><strong>Classificação Modal:</strong> {{ $movie->modal_rating }}</p>
                        <p><strong>Classificação Mediana:</strong> {{ $movie->median_rating }}</p>
                        <p><strong>Total de Avaliações:</strong> {{ $movie->total_ratings }}</p>
                        <a href="{{ route('site.home') }}" class="btn btn-primary">Voltar</a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <h3>Avaliações</h3>
                        @foreach($movie->users as $user)
                        <div class="card">
                            <div class="card-body d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">{{ $user->name }}</h5>
                                    <p class="card-text">{{ $user->pivot->rating }}</p>
                                    <p class="card-text">{{ $user->pivot->review }}</p>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </main>

@endsection
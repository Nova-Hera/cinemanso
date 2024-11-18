@extends('layout.site')
@section('title','Login')
@section('content')
    <main>
        <section id="login">
            <div class="container">
                <div class="row">
                    <div class="col-md-6 offset-md-3">
                        <h2 class="text-center box-title">Login</h2>
                        <form action="{{ route('login') }}" method="post" class="box-form">
                            @csrf
                            <div class="box-input">
                                <label for="email" class="form-label">E-mail</label>
                                <span>
                                    <input type="email" id="email" name="email">
                                </span>
                            </div>
                            <div class="box-input">
                                <label for="password" class="form-label">Senha</label>
                                <span>
                                    <input type="password" id="password" name="password">
                                </span>
                            </div>
                            <button type="submit" class="form-confirm btn">Entrar</button>
                        </form>

                        <a href="{{ route('signup') }}" class="btn right" style="color: #fff; font-size: 0.8rem">Criar uma conta</a>
                    </div>
                </div>
            </div>
        </section>
    </main>
@endsection
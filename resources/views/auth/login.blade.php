@extends('layouts.app')
@section('title', 'Logowanie')
@section('content')
<div class="card" style="max-width:420px;margin:60px auto">
    <h1 style="margin-top:0">Logowanie</h1>
    <form method="post" action="{{ url('/logowanie') }}">
        @csrf
        <label for="email">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email')<div class="error">{{ $message }}</div>@enderror

        <label for="password">Hasło</label>
        <input id="password" type="password" name="password" required>
        @error('password')<div class="error">{{ $message }}</div>@enderror

        <label style="font-weight:400"><input type="checkbox" name="remember" value="1"> Zapamiętaj mnie</label>
        <p><button class="btn btn-primary" type="submit">Zaloguj</button></p>
    </form>
</div>
@endsection

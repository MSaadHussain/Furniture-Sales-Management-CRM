@extends('layouts.app')
@section('title', 'Register User')
@section('breadcrumb')
    <a href="{{ route('users.index') }}" class="hover:text-brand">Users</a> / Register
@endsection

@section('content')
    @include('users._form')
@endsection

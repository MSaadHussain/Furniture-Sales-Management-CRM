@extends('layouts.app')
@section('title', 'New Customer')
@section('breadcrumb')
    <a href="{{ route('customers.index') }}" class="hover:text-brand">Customers</a> / New
@endsection

@section('content')
    @include('customers._form')
@endsection

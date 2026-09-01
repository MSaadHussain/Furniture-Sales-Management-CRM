@extends('layouts.app')
@section('title', 'New Product')
@section('breadcrumb')
    <a href="{{ route('products.index') }}" class="hover:text-brand">Products</a> / New
@endsection

@section('content')
    @include('products._form')
@endsection

@extends('layouts.app')
@section('title', 'Edit ' . $product->name)
@section('breadcrumb')
    <a href="{{ route('products.index') }}" class="hover:text-brand">Products</a> / {{ $product->name }} / Edit
@endsection

@section('content')
    @include('products._form')
@endsection

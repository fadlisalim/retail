@extends('layouts.admin')

@section('title', 'Ubah FAQ')

@section('content')
    <x-admin.page-header title="Ubah FAQ" :subtitle="$faq->question">
        <x-slot:actions>
            <a href="{{ route('admin.faqs.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.faqs.update', $faq) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.faqs._form')
    </form>
@endsection

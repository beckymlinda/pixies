@extends('layouts.app')

@section('content')
    <div class="container-fluid px-4 py-4">
        @livewire(App\Http\Livewire\DirectorStockEntryForm::class)
    </div>
@endsection

@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Name') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection
@section('tbody')
    @foreach ($setup['items'] as $item)
        <tr>
            <td>{{ $item->name }}</td>
           
           
            
            <td>
                <!-- EDIT -->
                <a href="{{ route('flowmaker.edit',['flow'=>$item->id]) }}" class="btn btn-success btn-sm">
                    <i class="ni ni-ruler-pencil"></i> {{ __('Flow maker')}}
                </a>

                <!-- EDIT -->
                <a href="{{ route('flows.edit',['flow'=>$item->id]) }}" class="btn btn-primary btn-sm">
                    <i class="ni ni-ruler-pencil"></i>
                </a>

                <!-- DELETE -->
                <a href="{{ route('flows.delete',['flow'=>$item->id]) }}" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this flow?')">
                    <i class="ni ni ni-fat-remove"></i>
                </a>
            </td>
          
        </tr> 
    @endforeach
@endsection
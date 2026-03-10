@extends('layouts.medforge')

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Usuarios</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Usuarios</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <a href="/v2/roles" class="btn btn-outline-primary btn-sm">Administrar roles</a>
        </div>
    </div>

    <section class="content">
        @if(($status ?? null) === 'updated')
            <div class="alert alert-success">Usuario actualizado correctamente.</div>
        @elseif(($status ?? null) === 'not_found')
            <div class="alert alert-danger">El usuario no existe.</div>
        @endif

        <div class="box">
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover table-sm align-middle">
                    <thead class="bg-primary">
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Permisos efectivos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td>{{ $user['id'] }}</td>
                                <td>{{ $user['username'] }}</td>
                                <td>{{ $user['nombre'] !== '' ? $user['nombre'] : '—' }}</td>
                                <td>{{ $user['email'] !== '' ? $user['email'] : '—' }}</td>
                                <td>{{ $user['role_name'] !== '' ? $user['role_name'] : 'Sin rol' }}</td>
                                <td>
                                    @if(empty($user['effective_permissions']))
                                        <span class="badge bg-secondary">Sin permisos</span>
                                    @else
                                        @foreach($user['effective_permissions'] as $permission)
                                            <span class="badge bg-light text-dark border me-1 mb-1">
                                                {{ $permissionLabels[$permission] ?? $permission }}
                                            </span>
                                        @endforeach
                                    @endif
                                </td>
                                <td>
                                    <a href="/v2/usuarios/{{ $user['id'] }}/edit" class="btn btn-sm btn-primary">Editar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No hay usuarios registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

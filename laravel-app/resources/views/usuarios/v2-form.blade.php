@php
    /** @var array<string, mixed> $user */
    /** @var array<int, array{id:int,name:string}> $roles */
    /** @var array<string, array<string, string>> $permissions */
    /** @var array<int, string> $selectedPermissions */
    /** @var array<string, string> $validationErrors */
    /** @var array<int, string> $warnings */
    /** @var string $formAction */
    /** @var string $mode */

    $user = $user ?? [];
    $roles = $roles ?? [];
    $permissions = $permissions ?? [];
    $selectedPermissions = $selectedPermissions ?? [];
    $validationErrors = $validationErrors ?? [];
    $warnings = $warnings ?? [];
    $mode = $mode ?? 'edit';
    $isCreate = $mode === 'create';
    $canAssignSuperuser = !empty($canAssignSuperuser);

    $fieldValue = static function (string $key, string $default = '') use ($user): string {
        return htmlspecialchars((string) ($user[$key] ?? $default), ENT_QUOTES, 'UTF-8');
    };

    $isChecked = static function (string $key) use ($user): string {
        return !empty($user[$key]) ? 'checked' : '';
    };

    $fullName = trim((string) ($user['display_full_name'] ?? ''));
    $especialidades = [
        '' => 'Seleccionar',
        'Cirujano Oftalmólogo' => 'Cirujano Oftalmólogo',
        'Anestesiologo' => 'Anestesiólogo',
        'Asistente' => 'Asistente',
        'Optometrista' => 'Optometrista',
        'Enfermera' => 'Enfermera',
        'Administrativo' => 'Administrativo',
        'Facturación' => 'Facturación',
        'Sistemas' => 'Sistemas',
        'Coordinación Quirúrgica' => 'Coordinación Quirúrgica',
        'Admisión' => 'Admisión',
        'Imagenología' => 'Imagenología',
    ];
@endphp

@extends('layouts.medforge')

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">{{ $isCreate ? 'Nuevo usuario' : 'Editar usuario' }}</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/usuarios">Usuarios</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $isCreate ? 'Nuevo' : ($user['username'] ?? 'Editar') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <a href="/usuarios" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>
    </div>

    <section class="content">
        @if(($status ?? null) === 'updated')
            <div class="alert alert-success">Cambios guardados correctamente.</div>
        @endif

        @if($warnings !== [])
            <div class="alert alert-warning">
                <p class="mb-2 fw-semibold"><i class="mdi mdi-alert"></i> Posibles duplicados detectados:</p>
                <ul class="mb-0 ps-3">
                    @foreach($warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($validationErrors !== [])
            <div class="alert alert-danger" role="alert" aria-live="assertive" tabindex="-1" data-validation-alert>
                <i class="mdi mdi-alert-circle-outline"></i> Revisa los campos marcados para continuar.
                @if(!empty($validationErrors['general']))
                    <div class="mt-2">{{ $validationErrors['general'] }}</div>
                @endif
            </div>
        @endif

        <div class="box">
            <div class="box-body">
                <div id="userUploadA11yStatus" class="visually-hidden" aria-live="polite"></div>
                <form action="{{ $formAction }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre de usuario *</label>
                            <input type="text" name="username" class="form-control" value="{!! $fieldValue('username') !!}" required>
                            @if(!empty($validationErrors['username']))
                                <div class="text-danger small">{{ $validationErrors['username'] }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" name="email" class="form-control" value="{!! $fieldValue('email') !!}">
                            @if(!empty($validationErrors['email']))
                                <div class="text-danger small">{{ $validationErrors['email'] }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">WhatsApp del agente</label>
                            <input type="text" name="whatsapp_number" class="form-control" value="{!! $fieldValue('whatsapp_number') !!}" placeholder="+593...">
                            <small class="text-muted">Se usará para notificaciones de handoff.</small>
                            @if(!empty($validationErrors['whatsapp_number']))
                                <div class="text-danger small">{{ $validationErrors['whatsapp_number'] }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label d-block">Notificaciones WhatsApp</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="whatsapp_notify" name="whatsapp_notify" {{ $isChecked('whatsapp_notify') }}>
                                <label class="form-check-label" for="whatsapp_notify">Recibir alertas de handoff</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="first_name" class="form-control" maxlength="100"
                                   pattern="[A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-'\"\.\s]+"
                                   value="{!! $fieldValue('first_name') !!}" required>
                            @if(!empty($validationErrors['first_name']))
                                <div class="text-danger small">{{ $validationErrors['first_name'] }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Segundo nombre</label>
                            <input type="text" name="middle_name" class="form-control" maxlength="100"
                                   pattern="[A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-'\"\.\s]+"
                                   value="{!! $fieldValue('middle_name') !!}">
                            @if(!empty($validationErrors['middle_name']))
                                <div class="text-danger small">{{ $validationErrors['middle_name'] }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Primer apellido *</label>
                            <input type="text" name="last_name" class="form-control" maxlength="100"
                                   pattern="[A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-'\"\.\s]+"
                                   value="{!! $fieldValue('last_name') !!}" required>
                            @if(!empty($validationErrors['last_name']))
                                <div class="text-danger small">{{ $validationErrors['last_name'] }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Segundo apellido</label>
                            <input type="text" name="second_last_name" class="form-control" maxlength="100"
                                   pattern="[A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-'\"\.\s]+"
                                   value="{!! $fieldValue('second_last_name') !!}">
                            @if(!empty($validationErrors['second_last_name']))
                                <div class="text-danger small">{{ $validationErrors['second_last_name'] }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Fecha de nacimiento</label>
                            <input type="date" name="birth_date" class="form-control" value="{!! $fieldValue('birth_date') !!}">
                            @if(!empty($validationErrors['birth_date']))
                                <div class="text-danger small">{{ $validationErrors['birth_date'] }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Identificación nacional</label>
                            <input type="text" name="national_id" class="form-control" maxlength="32" pattern="[A-Za-z0-9-]{4,32}"
                                   value="{!! $fieldValue('national_id') !!}" placeholder="{{ $isCreate ? 'Letras, números y guiones' : 'Se mantiene si se deja en blanco al editar' }}">
                            <small class="text-muted">Se almacena de forma protegida. Usa solo letras, números y guiones.</small>
                            @if(!empty($user['national_id_masked']))
                                <div class="text-muted small">Actual: {{ $user['national_id_masked'] }}</div>
                            @endif
                            @if(!empty($validationErrors['national_id']))
                                <div class="text-danger small">{{ $validationErrors['national_id'] }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Pasaporte</label>
                            <input type="text" name="passport_number" class="form-control" maxlength="32" pattern="[A-Za-z0-9-]{4,32}"
                                   value="{!! $fieldValue('passport_number') !!}" placeholder="{{ $isCreate ? 'Letras, números y guiones' : 'Se mantiene si se deja en blanco al editar' }}">
                            <small class="text-muted">Se almacena de forma protegida. Usa solo letras, números y guiones.</small>
                            @if(!empty($user['passport_number_masked']))
                                <div class="text-muted small">Actual: {{ $user['passport_number_masked'] }}</div>
                            @endif
                            @if(!empty($validationErrors['passport_number']))
                                <div class="text-danger small">{{ $validationErrors['passport_number'] }}</div>
                            @endif
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Nombre completo</label>
                            <input type="text" id="display_full_name" class="form-control" value="{{ $fullName }}" readonly>
                            <small class="text-muted">Se compone automáticamente a partir de los nombres y apellidos.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Cédula</label>
                            <input type="text" name="cedula" class="form-control" value="{!! $fieldValue('cedula') !!}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Registro</label>
                            <input type="text" name="registro" class="form-control" value="{!! $fieldValue('registro') !!}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Sede</label>
                            <input type="text" name="sede" class="form-control" value="{!! $fieldValue('sede') !!}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="especialidad">Especialidad</label>
                            <select name="especialidad" id="especialidad" class="form-select">
                                @foreach($especialidades as $value => $label)
                                    <option value="{{ $value }}" {{ ($user['especialidad'] ?? '') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="subespecialidad">Subespecialidad</label>
                            <input type="text" name="subespecialidad" id="subespecialidad" class="form-control" value="{!! $fieldValue('subespecialidad') !!}">
                            <small class="text-muted">Se habilita solo para Cirujano Oftalmólogo.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Contraseña {{ $isCreate ? '*' : '(dejar en blanco para mantener)' }}</label>
                            <input type="password" name="password" class="form-control" autocomplete="new-password">
                            @if(!empty($validationErrors['password']))
                                <div class="text-danger small">{{ $validationErrors['password'] }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Foto de perfil</label>
                            @if(!empty($user['profile_photo_url']))
                                <div class="mb-2">
                                    <img src="{{ $user['profile_photo_url'] }}" alt="Foto de perfil actual" class="img-thumbnail" style="max-height: 120px;">
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="remove_profile_photo" id="remove_profile_photo" value="1">
                                    <label class="form-check-label" for="remove_profile_photo">Eliminar foto actual</label>
                                </div>
                            @endif
                            <div class="drop-zone mt-2 p-3 border rounded" data-upload-drop-zone="profile_photo_file" tabindex="0" aria-label="Zona de carga para foto de perfil" aria-describedby="profile_photo_help">
                                <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                                    <div class="fw-semibold">Arrastra tu foto o utiliza el botón para explorar archivos.</div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-upload-trigger="profile_photo_file">
                                        <i class="mdi mdi-upload"></i> Seleccionar foto
                                    </button>
                                </div>
                                <div class="progress progress-xs mt-2 d-none" data-upload-progress="profile_photo_file" aria-hidden="true">
                                    <div class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                                </div>
                                <div class="text-danger small mt-2 {{ !empty($validationErrors['profile_photo_file']) ? '' : 'd-none' }}" data-upload-error="profile_photo_file" role="alert">
                                    {{ $validationErrors['profile_photo_file'] ?? '' }}
                                </div>
                                <div class="mt-2" data-upload-preview="profile_photo_file"></div>
                                <div class="text-muted small mt-2" id="profile_photo_help">Formatos permitidos: PNG, JPG o WEBP. Máximo 2 MB. Recomendado 400x400 px.</div>
                                <input type="file" name="profile_photo_file" id="profile_photo_file" class="form-control mt-2" accept="image/png,image/jpeg,image/webp">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Sello</label>
                            @if(!empty($user['firma_url']))
                                <div class="mb-2">
                                    <img src="{{ $user['firma_url'] }}" alt="Sello actual" class="img-fluid border rounded" style="max-height: 120px;">
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="remove_firma" id="remove_firma" value="1">
                                    <label class="form-check-label" for="remove_firma">Eliminar sello actual</label>
                                </div>
                            @endif
                            <div class="drop-zone mt-2 p-3 border rounded" data-upload-drop-zone="firma_file" tabindex="0" aria-label="Zona de carga para sello" aria-describedby="firma_help">
                                <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                                    <div class="fw-semibold">Arrastra y suelta tu sello o usa el botón de selección.</div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-upload-trigger="firma_file">
                                        <i class="mdi mdi-upload"></i> Seleccionar archivo
                                    </button>
                                </div>
                                <div class="progress progress-xs mt-2 d-none" data-upload-progress="firma_file" aria-hidden="true">
                                    <div class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                                </div>
                                <div class="text-danger small mt-2 {{ !empty($validationErrors['firma_file']) ? '' : 'd-none' }}" data-upload-error="firma_file" role="alert">
                                    {{ $validationErrors['firma_file'] ?? '' }}
                                </div>
                                <div class="mt-2" data-upload-preview="firma_file"></div>
                                <div class="text-muted small mt-2" id="firma_help">Formatos permitidos: PNG, WEBP o SVG. Máximo 2 MB.</div>
                                <input type="file" name="firma_file" id="firma_file" class="form-control mt-2" accept="image/png,image/webp,image/svg+xml">
                            </div>
                            <div class="mt-2">
                                <label class="form-label mb-1">Estado del sello</label>
                                <select name="seal_status" class="form-select form-select-sm">
                                    <option value="pending" {{ ($user['seal_status'] ?? 'pending') === 'pending' ? 'selected' : '' }}>Pendiente de revisión</option>
                                    <option value="verified" {{ ($user['seal_status'] ?? 'pending') === 'verified' ? 'selected' : '' }}>Verificado</option>
                                    <option value="not_provided" {{ ($user['seal_status'] ?? 'pending') === 'not_provided' ? 'selected' : '' }}>No proporcionado</option>
                                </select>
                                @if(!empty($validationErrors['seal_status']))
                                    <div class="text-danger small">{{ $validationErrors['seal_status'] }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Firma digital</label>
                            @if(!empty($user['signature_url']))
                                <div class="mb-2">
                                    <img src="{{ $user['signature_url'] }}" alt="Firma digital actual" class="img-fluid border rounded" style="max-height: 120px;">
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="remove_signature" id="remove_signature" value="1">
                                    <label class="form-check-label" for="remove_signature">Eliminar firma digital actual</label>
                                </div>
                            @endif
                            <div class="drop-zone mt-2 p-3 border rounded" data-upload-drop-zone="signature_file" tabindex="0" aria-label="Zona de carga para firma digital" aria-describedby="signature_help">
                                <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                                    <div class="fw-semibold">Arrastra o pega tu firma digital para previsualizarla.</div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-upload-trigger="signature_file">
                                        <i class="mdi mdi-upload"></i> Seleccionar archivo
                                    </button>
                                </div>
                                <div class="progress progress-xs mt-2 d-none" data-upload-progress="signature_file" aria-hidden="true">
                                    <div class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                                </div>
                                <div class="text-danger small mt-2 {{ !empty($validationErrors['signature_file']) ? '' : 'd-none' }}" data-upload-error="signature_file" role="alert">
                                    {{ $validationErrors['signature_file'] ?? '' }}
                                </div>
                                <div class="mt-2" data-upload-preview="signature_file"></div>
                                <div class="text-muted small mt-2" id="signature_help">Formatos permitidos: PNG, WEBP o SVG. Máximo 2 MB.</div>
                                <input type="file" name="signature_file" id="signature_file" class="form-control mt-2" accept="image/png,image/webp,image/svg+xml">
                            </div>
                            <div class="mt-2">
                                <label class="form-label mb-1">Estado de la firma</label>
                                <select name="signature_status" class="form-select form-select-sm">
                                    <option value="pending" {{ ($user['signature_status'] ?? 'pending') === 'pending' ? 'selected' : '' }}>Pendiente de revisión</option>
                                    <option value="verified" {{ ($user['signature_status'] ?? 'pending') === 'verified' ? 'selected' : '' }}>Verificada</option>
                                    <option value="not_provided" {{ ($user['signature_status'] ?? 'pending') === 'not_provided' ? 'selected' : '' }}>No proporcionada</option>
                                </select>
                                @if(!empty($validationErrors['signature_status']))
                                    <div class="text-danger small">{{ $validationErrors['signature_status'] }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Sello + firma combinados</label>
                            @if(!empty($user['seal_signature_url']))
                                <div class="mb-2">
                                    <img src="{{ $user['seal_signature_url'] }}" alt="Sello y firma combinados" class="img-fluid border rounded" style="max-height: 120px;">
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="remove_seal_signature" id="remove_seal_signature" value="1">
                                    <label class="form-check-label" for="remove_seal_signature">Eliminar imagen combinada actual</label>
                                </div>
                            @endif
                            <div class="drop-zone mt-2 p-3 border rounded" data-upload-drop-zone="seal_signature_file" tabindex="0" aria-label="Zona de carga para sello y firma combinados" aria-describedby="seal_signature_help">
                                <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                                    <div class="fw-semibold">Sube una sola imagen con sello y firma ya combinados.</div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-upload-trigger="seal_signature_file">
                                        <i class="mdi mdi-upload"></i> Seleccionar archivo
                                    </button>
                                </div>
                                <div class="progress progress-xs mt-2 d-none" data-upload-progress="seal_signature_file" aria-hidden="true">
                                    <div class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                                </div>
                                <div class="text-danger small mt-2 {{ !empty($validationErrors['seal_signature_file']) ? '' : 'd-none' }}" data-upload-error="seal_signature_file" role="alert">
                                    {{ $validationErrors['seal_signature_file'] ?? '' }}
                                </div>
                                <div class="mt-2" data-upload-preview="seal_signature_file"></div>
                                <div class="text-muted small mt-2" id="seal_signature_help">Formatos permitidos: PNG, WEBP o SVG. Máximo 2 MB.</div>
                                <input type="file" name="seal_signature_file" id="seal_signature_file" class="form-control mt-2" accept="image/png,image/webp,image/svg+xml">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Rol</label>
                            <select name="role_id" class="form-select">
                                <option value="">Sin asignar</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role['id'] }}" {{ (int) ($user['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' }}>
                                        {{ $role['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @if(!empty($validationErrors['role_id']))
                                <div class="text-danger small">{{ $validationErrors['role_id'] }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_approved" id="is_approved" {{ $isChecked('is_approved') }}>
                                <label class="form-check-label" for="is_approved">Usuario aprobado</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_subscribed" id="is_subscribed" {{ $isChecked('is_subscribed') }}>
                                <label class="form-check-label" for="is_subscribed">Recibe notificaciones</label>
                            </div>
                        </div>
                    </div>

                    @if(!empty($permissionProfiles))
                        <hr class="my-4">
                        <div class="mb-4">
                            <label for="permission_profile" class="form-label">Plantilla rápida de permisos</label>
                            <div class="d-flex gap-2">
                                <select id="permission_profile" class="form-select">
                                    <option value="">Selecciona una plantilla…</option>
                                    @foreach($permissionProfiles as $profileKey => $profile)
                                        <option value="{{ $profileKey }}">
                                            {{ $profile['label'] ?? $profileKey }} - {{ $profile['description'] ?? '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" id="apply_permission_profile" class="btn btn-outline-primary">Aplicar</button>
                            </div>
                            <small class="text-muted">Reemplaza la selección actual de permisos directos por la plantilla.</small>
                        </div>
                    @endif

                    <hr class="my-4">
                    <div>
                        <h5 class="mb-3">Permisos</h5>
                        @if(!$canAssignSuperuser)
                            <div class="alert alert-warning">
                                El permiso <strong>superusuario</strong> queda bloqueado en este formulario. Solo otro superusuario puede otorgarlo o retirarlo.
                            </div>
                        @endif
                        @foreach($permissions as $group => $items)
                            <div class="mb-3">
                                <p class="fw-bold mb-2">{{ $group }}</p>
                                <div class="row g-2">
                                    @foreach($items as $permission => $label)
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                @php($permissionId = 'perm_' . preg_replace('/[^a-z0-9_-]/i', '_', $permission))
                                                @php($isSuperuserPermission = $permission === 'superuser')
                                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}" id="{{ $permissionId }}" {{ in_array($permission, $selectedPermissions, true) ? 'checked' : '' }} {{ $isSuperuserPermission && !$canAssignSuperuser ? 'disabled' : '' }}>
                                                <label class="form-check-label" for="{{ $permissionId }}">
                                                    {{ $label }}
                                                    @if($isSuperuserPermission)
                                                        <span class="badge bg-danger ms-1">Crítico</span>
                                                    @endif
                                                </label>
                                                @if($isSuperuserPermission)
                                                    <div class="small text-muted">
                                                        Otorga acceso irrestricto a todo el sistema y a la delegación de otros superusuarios.
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 d-flex flex-wrap justify-content-between gap-2">
                        <a href="/usuarios" class="btn btn-outline-secondary">Cancelar</a>
                        <div class="d-flex gap-2">
                            @if(!$isCreate && !empty($user['id']) && !empty($canDelete))
                                <button
                                    type="submit"
                                    class="btn btn-outline-danger"
                                    formaction="/usuarios/{{ (int) $user['id'] }}/delete"
                                    formmethod="POST"
                                    onclick="return confirm('¿Deseas eliminar a {{ addslashes((string) ($user['username'] ?? 'este usuario')) }}?');"
                                >
                                    Eliminar
                                </button>
                            @endif
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="/js/modules/user-media-upload.js"></script>
    <script>
        (function () {
            const profiles = @json($permissionProfiles ?? []);
            const applyButton = document.getElementById('apply_permission_profile');
            const profileSelect = document.getElementById('permission_profile');
            const permissionInputs = Array.from(document.querySelectorAll('input[name="permissions[]"]'));
            const especialidadSelect = document.getElementById('especialidad');
            const subespecialidadInput = document.getElementById('subespecialidad');
            const fullNameInput = document.getElementById('display_full_name');
            const nameInputs = [
                document.querySelector('input[name="first_name"]'),
                document.querySelector('input[name="middle_name"]'),
                document.querySelector('input[name="last_name"]'),
                document.querySelector('input[name="second_last_name"]'),
            ].filter(Boolean);

            if (applyButton && profileSelect) {
                applyButton.addEventListener('click', function () {
                    const key = profileSelect.value;
                    if (!key || !profiles[key] || !Array.isArray(profiles[key].permissions)) {
                        return;
                    }

                    const selected = new Set(profiles[key].permissions);
                    permissionInputs.forEach(function (input) {
                        input.checked = selected.has(input.value);
                    });
                });
            }

            const toggleSubespecialidad = function () {
                if (!especialidadSelect || !subespecialidadInput) {
                    return;
                }

                const isOftalmologo = especialidadSelect.value === 'Cirujano Oftalmólogo';
                subespecialidadInput.disabled = !isOftalmologo;
                if (!isOftalmologo) {
                    subespecialidadInput.value = '';
                }
            };

            const updateFullName = function () {
                if (!fullNameInput) {
                    return;
                }

                const value = nameInputs
                    .map(function (input) { return (input.value || '').trim(); })
                    .filter(Boolean)
                    .join(' ');

                fullNameInput.value = value;
            };

            if (especialidadSelect) {
                especialidadSelect.addEventListener('change', toggleSubespecialidad);
                toggleSubespecialidad();
            }

            nameInputs.forEach(function (input) {
                input.addEventListener('input', updateFullName);
            });
        })();
    </script>
@endpush

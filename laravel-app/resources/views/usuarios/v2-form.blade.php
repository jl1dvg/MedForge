@php
    /** @var array<string, mixed>               $user */
    /** @var array<int, array{id:int,name:string}> $roles */
    /** @var array<string, array<string, string>>  $permissions */
    /** @var array<int, string>                 $selectedPermissions */
    /** @var array<string, list<string>>        $rolesWithPermissions */
    /** @var array<string, string>              $validationErrors */
    /** @var array<int, string>                 $warnings */
    /** @var string                             $formAction */
    /** @var string                             $mode */

    $user                = $user                ?? [];
    $roles               = $roles               ?? [];
    $permissions         = $permissions         ?? [];
    $selectedPermissions = $selectedPermissions ?? [];
    $rolesWithPermissions = $rolesWithPermissions ?? [];
    $validationErrors    = $validationErrors    ?? [];
    $warnings            = $warnings            ?? [];
    $mode                = $mode                ?? 'edit';
    $isCreate            = $mode === 'create';
    $canAssignSuperuser  = !empty($canAssignSuperuser);

    /* ── Helper closures ───────────────────────────────────────────────── */
    $fieldValue = static function (string $key, string $default = '') use ($user): string {
        return htmlspecialchars((string) ($user[$key] ?? $default), ENT_QUOTES, 'UTF-8');
    };

    $isChecked = static function (string $key) use ($user): string {
        return !empty($user[$key]) ? 'checked' : '';
    };

    /* ── Identity fields ───────────────────────────────────────────────── */
    $fullName   = trim((string) ($user['display_full_name'] ?? ''));
    $handle     = (string) ($user['username'] ?? '');
    $isApproved = !empty($user['is_approved']);
    $avatarInitials = strtoupper(
        substr((string) ($user['first_name'] ?? 'N'), 0, 1) .
        substr((string) ($user['last_name']  ?? ''),  0, 1)
    ) ?: 'N';

    /* ── Especialidades list ───────────────────────────────────────────── */
    $especialidades = [
        '' => 'Seleccionar',
        'Cirujano Oftalmólogo' => 'Cirujano Oftalmólogo',
        'Residente' => 'Residente',
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

    /* ── Multi-select config ───────────────────────────────────────────── */
    $subspecialties = $subspecialties ?? [];
    $sedesConfig    = $sedesConfig    ?? [];
    $selectedSedes  = array_filter(array_map('trim', explode(',', (string) ($user['sede'] ?? ''))));
    $selectedSubs   = array_filter(array_map('trim', explode(',', (string) ($user['subespecialidad'] ?? ''))));
    $isOftalmologo  = ($user['especialidad'] ?? '') === 'Cirujano Oftalmólogo';

    /* ── Direct permissions for JS: selected minus inherited by current role */
    $inheritedByCurrentRole = $rolesWithPermissions[(string) ($user['role_id'] ?? '')] ?? [];
    $inheritedSet           = array_flip($inheritedByCurrentRole);
    $directPermissions      = array_values(array_filter(
        $selectedPermissions,
        fn($p) => !isset($inheritedSet[$p])
    ));
@endphp

@extends('layouts.medforge')

@section('content')

{{-- ── Content header ──────────────────────────────────────────────────── --}}
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">{{ $isCreate ? 'Nuevo usuario' : 'Editar usuario' }}</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                    <li class="breadcrumb-item"><a href="/usuarios">Usuarios</a></li>
                    <li class="breadcrumb-item active" aria-current="page">
                        {{ $isCreate ? 'Nuevo' : ($handle ?: 'Editar') }}
                    </li>
                </ol>
            </nav>
        </div>
        <a href="/usuarios" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<section class="content">

    {{-- Alerts --}}
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

    {{-- ── User header ────────────────────────────────────────────────── --}}
    <div class="form-user-header">
        <div class="form-user-avatar">
            @if(!$isCreate && !empty($user['profile_photo_url']))
                <img src="{{ $user['profile_photo_url'] }}" alt="Foto de perfil">
            @else
                {{ $avatarInitials }}
            @endif
        </div>
        <div>
            <div class="form-user-name">
                {{ $isCreate ? 'Nuevo usuario' : ($fullName ?: $handle) }}
            </div>
            @if(!$isCreate)
                <div class="form-user-sub">
                    <span>{{ $handle }}</span>
                    @if($isApproved)
                        <span class="form-user-badge-approved">✓ Aprobado</span>
                    @else
                        <span class="form-user-badge-pending">● Pendiente</span>
                    @endif
                    @if(!empty($user['especialidad']))
                        <span>· {{ $user['especialidad'] }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- ── Tab bar ─────────────────────────────────────────────────────── --}}
    <div class="form-tab-bar" role="tablist" aria-label="Secciones del perfil">
        <button class="form-tab-btn active"
                data-tab="perfil"
                id="tab-btn-perfil"
                role="tab"
                aria-selected="true"
                aria-controls="tab-panel-perfil"
                type="button">
            Perfil
        </button>
        <button class="form-tab-btn"
                data-tab="acceso"
                id="tab-btn-acceso"
                role="tab"
                aria-selected="false"
                aria-controls="tab-panel-acceso"
                type="button">
            Acceso
        </button>
        <button class="form-tab-btn"
                data-tab="documentos"
                id="tab-btn-documentos"
                role="tab"
                aria-selected="false"
                aria-controls="tab-panel-documentos"
                type="button">
            Documentos
        </button>
    </div>

    {{-- ═══ FORM wraps all tabs ═══════════════════════════════════════════ --}}
    <div id="userUploadA11yStatus" class="visually-hidden" aria-live="polite"></div>

    <form action="{{ $formAction }}" method="POST" enctype="multipart/form-data">
        @csrf

        {{-- ════════════════════════════════════════════════════════════
             TAB: PERFIL
             ════════════════════════════════════════════════════════════ --}}
        <div class="form-tab-panel"
             id="tab-panel-perfil"
             data-tab="perfil"
             role="tabpanel"
             aria-labelledby="tab-btn-perfil">

            {{-- Identidad --}}
            <div class="form-section-label">Identidad</div>
            <div class="form-field-grid">

                {{-- Usuario --}}
                <div>
                    <label class="form-label" for="username">Usuario</label>
                    <input type="text"
                           name="username"
                           id="username"
                           class="form-control"
                           value="{!! $fieldValue('username') !!}"
                           {{ $isCreate ? 'required' : 'readonly' }}>
                    @if(!empty($validationErrors['username']))
                        <div class="text-danger small">{{ $validationErrors['username'] }}</div>
                    @endif
                </div>

                {{-- Correo electrónico --}}
                <div>
                    <label class="form-label" for="email">Correo electrónico</label>
                    <input type="email"
                           name="email"
                           id="email"
                           class="form-control"
                           value="{!! $fieldValue('email') !!}">
                    @if(!empty($validationErrors['email']))
                        <div class="text-danger small">{{ $validationErrors['email'] }}</div>
                    @endif
                </div>

                {{-- Especialidad --}}
                <div>
                    <label class="form-label" for="especialidad">Especialidad</label>
                    <select name="especialidad" id="especialidad" class="form-select">
                        @foreach($especialidades as $value => $label)
                            <option value="{{ $value }}"
                                    {{ ($user['especialidad'] ?? '') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Nombre --}}
                <div>
                    <label class="form-label" for="first_name">Nombre *</label>
                    <input type="text"
                           name="first_name"
                           id="first_name"
                           class="form-control"
                           maxlength="100"
                           value="{!! $fieldValue('first_name') !!}"
                           required>
                    @if(!empty($validationErrors['first_name']))
                        <div class="text-danger small">{{ $validationErrors['first_name'] }}</div>
                    @endif
                </div>

                {{-- Segundo nombre --}}
                <div>
                    <label class="form-label" for="middle_name">Segundo nombre</label>
                    <input type="text"
                           name="middle_name"
                           id="middle_name"
                           class="form-control"
                           maxlength="100"
                           value="{!! $fieldValue('middle_name') !!}">
                    @if(!empty($validationErrors['middle_name']))
                        <div class="text-danger small">{{ $validationErrors['middle_name'] }}</div>
                    @endif
                </div>

                {{-- Primer apellido --}}
                <div>
                    <label class="form-label" for="last_name">Primer apellido *</label>
                    <input type="text"
                           name="last_name"
                           id="last_name"
                           class="form-control"
                           maxlength="100"
                           value="{!! $fieldValue('last_name') !!}"
                           required>
                    @if(!empty($validationErrors['last_name']))
                        <div class="text-danger small">{{ $validationErrors['last_name'] }}</div>
                    @endif
                </div>

                {{-- Segundo apellido --}}
                <div>
                    <label class="form-label" for="second_last_name">Segundo apellido</label>
                    <input type="text"
                           name="second_last_name"
                           id="second_last_name"
                           class="form-control"
                           maxlength="100"
                           value="{!! $fieldValue('second_last_name') !!}">
                    @if(!empty($validationErrors['second_last_name']))
                        <div class="text-danger small">{{ $validationErrors['second_last_name'] }}</div>
                    @endif
                </div>

                {{-- Fecha de nacimiento --}}
                <div>
                    <label class="form-label" for="birth_date">Fecha de nacimiento</label>
                    <input type="date"
                           name="birth_date"
                           id="birth_date"
                           class="form-control"
                           value="{!! $fieldValue('birth_date') !!}">
                    @if(!empty($validationErrors['birth_date']))
                        <div class="text-danger small">{{ $validationErrors['birth_date'] }}</div>
                    @endif
                </div>

                {{-- Identificación nacional --}}
                <div>
                    <label class="form-label" for="national_id">Identificación nacional</label>
                    <input type="text"
                           name="national_id"
                           id="national_id"
                           class="form-control"
                           maxlength="32"
                           value="{!! $fieldValue('national_id') !!}"
                           placeholder="{{ $isCreate ? 'Letras, números y guiones' : 'Se mantiene si se deja en blanco' }}">
                    <small class="text-muted">Almacenada de forma protegida.</small>
                    @if(!empty($user['national_id_masked']))
                        <div class="text-muted small">Actual: {{ $user['national_id_masked'] }}</div>
                    @endif
                    @if(!empty($validationErrors['national_id']))
                        <div class="text-danger small">{{ $validationErrors['national_id'] }}</div>
                    @endif
                </div>

                {{-- Pasaporte --}}
                <div>
                    <label class="form-label" for="passport_number">Pasaporte</label>
                    <input type="text"
                           name="passport_number"
                           id="passport_number"
                           class="form-control"
                           maxlength="32"
                           value="{!! $fieldValue('passport_number') !!}"
                           placeholder="{{ $isCreate ? 'Letras, números y guiones' : 'Se mantiene si se deja en blanco' }}">
                    <small class="text-muted">Almacenado de forma protegida.</small>
                    @if(!empty($user['passport_number_masked']))
                        <div class="text-muted small">Actual: {{ $user['passport_number_masked'] }}</div>
                    @endif
                    @if(!empty($validationErrors['passport_number']))
                        <div class="text-danger small">{{ $validationErrors['passport_number'] }}</div>
                    @endif
                </div>

                {{-- Cédula --}}
                <div>
                    <label class="form-label" for="cedula">Cédula</label>
                    <input type="text"
                           name="cedula"
                           id="cedula"
                           class="form-control"
                           value="{!! $fieldValue('cedula') !!}">
                </div>

                {{-- Registro --}}
                <div>
                    <label class="form-label" for="registro">Registro</label>
                    <input type="text"
                           name="registro"
                           id="registro"
                           class="form-control"
                           value="{!! $fieldValue('registro') !!}">
                </div>

                {{-- Sede (multi-select checkboxes) --}}
                <div class="col-full">
                    <label class="form-label mb-1">Sede</label>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach($sedesConfig as $sedeId => $sedeName)
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="sede[]"
                                   id="sede_{{ $sedeId }}"
                                   value="{{ $sedeId }}"
                                   {{ in_array((string) $sedeId, $selectedSedes) ? 'checked' : '' }}>
                            <label class="form-check-label" for="sede_{{ $sedeId }}">{{ $sedeName }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Subespecialidad (multi-select checkboxes, only for Cirujano Oftalmólogo) --}}
                <div class="col-full" id="subespecialidad-group" @if(!$isOftalmologo) hidden @endif>
                    <label class="form-label mb-1">Subespecialidad</label>
                    <small class="text-muted d-block mb-2">Solo para Cirujano Oftalmólogo.</small>
                    <div class="perm-grid">
                        @foreach($subspecialties as $slug => $sub)
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="subespecialidad[]"
                                   id="sub_{{ $slug }}"
                                   value="{{ $slug }}"
                                   {{ in_array($slug, $selectedSubs) ? 'checked' : '' }}>
                            <label class="form-check-label" for="sub_{{ $slug }}">{{ $sub['label'] }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- WhatsApp --}}
                <div>
                    <label class="form-label" for="whatsapp_number">WhatsApp del agente</label>
                    <input type="text"
                           name="whatsapp_number"
                           id="whatsapp_number"
                           class="form-control"
                           value="{!! $fieldValue('whatsapp_number') !!}"
                           placeholder="+593...">
                    <small class="text-muted">Para notificaciones de handoff.</small>
                    @if(!empty($validationErrors['whatsapp_number']))
                        <div class="text-danger small">{{ $validationErrors['whatsapp_number'] }}</div>
                    @endif
                </div>

                {{-- Nombre completo (readonly computed) --}}
                <div class="col-full">
                    <label class="form-label" for="display_full_name">Nombre completo</label>
                    <input type="text"
                           id="display_full_name"
                           class="form-control"
                           value="{{ $fullName }}"
                           readonly>
                    <small class="text-muted">Se compone automáticamente a partir de los nombres y apellidos.</small>
                </div>

                {{-- Contraseña --}}
                <div>
                    <label class="form-label" for="password">
                        Contraseña {{ $isCreate ? '*' : '(dejar en blanco para mantener)' }}
                    </label>
                    <input type="password"
                           name="password"
                           id="password"
                           class="form-control"
                           autocomplete="new-password"
                           {{ $isCreate ? 'required' : '' }}>
                    @if(!empty($validationErrors['password']))
                        <div class="text-danger small">{{ $validationErrors['password'] }}</div>
                    @endif
                </div>

            </div>{{-- /form-field-grid --}}

            {{-- Foto de perfil --}}
            <div class="form-section-label">Foto de perfil</div>
            @if(!empty($user['profile_photo_url']))
                <div class="mb-2">
                    <img src="{{ $user['profile_photo_url'] }}"
                         alt="Foto de perfil actual"
                         class="img-thumbnail"
                         style="max-height:120px;">
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox"
                           name="remove_profile_photo" id="remove_profile_photo" value="1">
                    <label class="form-check-label" for="remove_profile_photo">Eliminar foto actual</label>
                </div>
            @endif
            <div class="drop-zone p-3 border rounded"
                 data-upload-drop-zone="profile_photo_file"
                 tabindex="0"
                 aria-label="Zona de carga para foto de perfil"
                 aria-describedby="profile_photo_help">
                <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                    <div class="fw-semibold">Arrastra tu foto o usa el botón para explorar archivos.</div>
                    <button type="button" class="btn btn-sm btn-outline-primary"
                            data-upload-trigger="profile_photo_file">
                        <i class="mdi mdi-upload"></i> Seleccionar foto
                    </button>
                </div>
                <div class="progress progress-xs mt-2 d-none"
                     data-upload-progress="profile_photo_file" aria-hidden="true">
                    <div class="progress-bar" role="progressbar" style="width:0%">0%</div>
                </div>
                <div class="text-danger small mt-2 {{ !empty($validationErrors['profile_photo_file']) ? '' : 'd-none' }}"
                     data-upload-error="profile_photo_file" role="alert">
                    {{ $validationErrors['profile_photo_file'] ?? '' }}
                </div>
                <div class="mt-2" data-upload-preview="profile_photo_file"></div>
                <div class="text-muted small mt-2" id="profile_photo_help">
                    PNG, JPG o WEBP · Máx 2 MB · Recomendado 400×400 px
                </div>
                <input type="file"
                       name="profile_photo_file"
                       id="profile_photo_file"
                       class="form-control mt-2"
                       accept="image/png,image/jpeg,image/webp">
            </div>

            {{-- Estado de la cuenta --}}
            <div class="form-section-label">Estado de la cuenta</div>
            <div class="d-flex flex-column gap-2">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="is_approved" id="is_approved"
                           {{ $isChecked('is_approved') }}>
                    <label class="form-check-label" for="is_approved">Cuenta aprobada</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="is_subscribed" id="is_subscribed"
                           {{ $isChecked('is_subscribed') }}>
                    <label class="form-check-label" for="is_subscribed">Suscripción activa</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="whatsapp_notify" id="whatsapp_notify"
                           {{ $isChecked('whatsapp_notify') }}>
                    <label class="form-check-label" for="whatsapp_notify">Notificaciones WhatsApp</label>
                </div>
            </div>

        </div>{{-- /tab-panel-perfil --}}

        {{-- ════════════════════════════════════════════════════════════
             TAB: ACCESO
             ════════════════════════════════════════════════════════════ --}}
        <div class="form-tab-panel"
             id="tab-panel-acceso"
             data-tab="acceso"
             role="tabpanel"
             aria-labelledby="tab-btn-acceso"
             hidden>

            {{-- Rol --}}
            <div class="form-section-label">Rol asignado</div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="form-role-id">Rol</label>
                    <select name="role_id" id="form-role-id" class="form-select">
                        <option value="">Sin asignar</option>
                        @foreach($roles as $role)
                            <option value="{{ $role['id'] }}"
                                    {{ (int) ($user['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' }}>
                                {{ $role['name'] }}
                            </option>
                        @endforeach
                    </select>
                    @if(!empty($validationErrors['role_id']))
                        <div class="text-danger small">{{ $validationErrors['role_id'] }}</div>
                    @endif
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="alert alert-info mb-0 py-2 px-3 small">
                        <i class="mdi mdi-information-outline"></i>
                        Al cambiar el rol, los permisos heredados se actualizan al instante.
                    </div>
                </div>
            </div>

            @if(!$canAssignSuperuser)
                <div class="alert alert-warning mb-3">
                    El permiso <strong>superusuario</strong> queda bloqueado en este formulario.
                    Solo otro superusuario puede otorgarlo o retirarlo.
                </div>
            @endif

            {{-- Plantilla rápida --}}
            @if(!empty($permissionProfiles))
                <div class="form-section-label">Plantilla rápida</div>
                <div class="d-flex gap-2 mb-2">
                    <select id="permission_profile" class="form-select">
                        <option value="">Selecciona una plantilla…</option>
                        @foreach($permissionProfiles as $profileKey => $profile)
                            <option value="{{ $profileKey }}">
                                {{ $profile['label'] ?? $profileKey }}
                                @if(!empty($profile['description']))
                                    — {{ $profile['description'] }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <button type="button" id="apply_permission_profile" class="btn btn-outline-primary">
                        Aplicar
                    </button>
                </div>
                <small class="text-muted d-block mb-3">
                    Reemplaza solo los permisos directos (no afecta los heredados del rol).
                </small>
            @endif

            {{-- Leyenda --}}
            <div class="form-section-label">Permisos</div>
            <div class="perm-legend mb-3 d-flex gap-3 align-items-center">
                <span><span class="perm-legend-direct">●</span> directo</span>
                <span><span class="perm-legend-inherited">○</span> heredado del rol (no editable)</span>
            </div>

            {{-- Accordion de grupos --}}
            @foreach($permissions as $group => $items)
                @php
                    $groupSlug = preg_replace('/[^a-z0-9]/i', '-', strtolower($group));
                @endphp
                <div class="perm-group">
                    <div class="perm-group-head"
                         role="button"
                         tabindex="0"
                         aria-expanded="false"
                         aria-controls="perm-body-{{ $groupSlug }}">
                        <span>{{ $group }}</span>
                        <i class="mdi mdi-chevron-down perm-chevron" aria-hidden="true"></i>
                    </div>
                    <div class="perm-group-body" id="perm-body-{{ $groupSlug }}" hidden>
                        <div class="row g-2">
                            @foreach($items as $permission => $label)
                                @php
                                    $permId            = 'perm_' . preg_replace('/[^a-z0-9_-]/i', '_', $permission);
                                    $isSuperuser       = $permission === 'superuser';
                                    $isSelected        = in_array($permission, $selectedPermissions, true);
                                    $isLockedSuperuser = $isSuperuser && !$canAssignSuperuser;
                                @endphp
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input perm-check"
                                               type="checkbox"
                                               name="permissions[]"
                                               value="{{ $permission }}"
                                               id="{{ $permId }}"
                                               {{ $isSelected ? 'checked' : '' }}
                                               {{ $isLockedSuperuser ? 'disabled data-superuser-locked' : '' }}>
                                        <label class="form-check-label" for="{{ $permId }}">
                                            {{ $label }}
                                            @if($isSuperuser)
                                                <span class="badge bg-danger ms-1">Crítico</span>
                                            @endif
                                        </label>
                                        @if($isSuperuser)
                                            <div class="small text-muted">
                                                Otorga acceso irrestricto a todo el sistema.
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

        </div>{{-- /tab-panel-acceso --}}

        {{-- ════════════════════════════════════════════════════════════
             TAB: DOCUMENTOS
             ════════════════════════════════════════════════════════════ --}}
        <div class="form-tab-panel"
             id="tab-panel-documentos"
             data-tab="documentos"
             role="tabpanel"
             aria-labelledby="tab-btn-documentos"
             hidden>

            <div class="form-section-label">Documentos médicos</div>
            <div class="form-upload-grid">

                {{-- Sello --}}
                <div>
                    <p class="fw-semibold mb-2">Sello</p>
                    @if(!empty($user['firma_url']))
                        <div class="mb-2">
                            <img src="{{ $user['firma_url'] }}"
                                 alt="Sello actual"
                                 class="img-fluid border rounded"
                                 style="max-height:120px;">
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox"
                                   name="remove_firma" id="remove_firma" value="1">
                            <label class="form-check-label" for="remove_firma">Eliminar sello actual</label>
                        </div>
                    @endif
                    <div class="drop-zone p-3 border rounded"
                         data-upload-drop-zone="firma_file"
                         tabindex="0"
                         aria-label="Zona de carga para sello"
                         aria-describedby="firma_help">
                        <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                            <div class="fw-semibold">Arrastra o usa el botón.</div>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-upload-trigger="firma_file">
                                <i class="mdi mdi-upload"></i> Seleccionar
                            </button>
                        </div>
                        <div class="progress progress-xs mt-2 d-none"
                             data-upload-progress="firma_file" aria-hidden="true">
                            <div class="progress-bar" role="progressbar" style="width:0%">0%</div>
                        </div>
                        <div class="text-danger small mt-2 {{ !empty($validationErrors['firma_file']) ? '' : 'd-none' }}"
                             data-upload-error="firma_file" role="alert">
                            {{ $validationErrors['firma_file'] ?? '' }}
                        </div>
                        <div class="mt-2" data-upload-preview="firma_file"></div>
                        <div class="text-muted small mt-2" id="firma_help">PNG, WEBP o SVG · Máx 2 MB</div>
                        <input type="file"
                               name="firma_file"
                               id="firma_file"
                               class="form-control mt-2"
                               accept="image/png,image/webp,image/svg+xml">
                    </div>
                    <div class="mt-2">
                        <label class="form-label mb-1" for="seal_status">Estado del sello</label>
                        <select name="seal_status" id="seal_status" class="form-select form-select-sm">
                            <option value="pending"      {{ ($user['seal_status'] ?? 'pending') === 'pending'      ? 'selected' : '' }}>Pendiente de revisión</option>
                            <option value="verified"     {{ ($user['seal_status'] ?? 'pending') === 'verified'     ? 'selected' : '' }}>Verificado</option>
                            <option value="not_provided" {{ ($user['seal_status'] ?? 'pending') === 'not_provided' ? 'selected' : '' }}>No proporcionado</option>
                        </select>
                        @if(!empty($validationErrors['seal_status']))
                            <div class="text-danger small">{{ $validationErrors['seal_status'] }}</div>
                        @endif
                    </div>
                </div>

                {{-- Firma digital --}}
                <div>
                    <p class="fw-semibold mb-2">Firma digital</p>
                    @if(!empty($user['signature_url']))
                        <div class="mb-2">
                            <img src="{{ $user['signature_url'] }}"
                                 alt="Firma digital actual"
                                 class="img-fluid border rounded"
                                 style="max-height:120px;">
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox"
                                   name="remove_signature" id="remove_signature" value="1">
                            <label class="form-check-label" for="remove_signature">Eliminar firma digital actual</label>
                        </div>
                    @endif
                    <div class="drop-zone p-3 border rounded"
                         data-upload-drop-zone="signature_file"
                         tabindex="0"
                         aria-label="Zona de carga para firma digital"
                         aria-describedby="signature_help">
                        <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                            <div class="fw-semibold">Arrastra o usa el botón.</div>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-upload-trigger="signature_file">
                                <i class="mdi mdi-upload"></i> Seleccionar
                            </button>
                        </div>
                        <div class="progress progress-xs mt-2 d-none"
                             data-upload-progress="signature_file" aria-hidden="true">
                            <div class="progress-bar" role="progressbar" style="width:0%">0%</div>
                        </div>
                        <div class="text-danger small mt-2 {{ !empty($validationErrors['signature_file']) ? '' : 'd-none' }}"
                             data-upload-error="signature_file" role="alert">
                            {{ $validationErrors['signature_file'] ?? '' }}
                        </div>
                        <div class="mt-2" data-upload-preview="signature_file"></div>
                        <div class="text-muted small mt-2" id="signature_help">PNG, WEBP o SVG · Máx 2 MB</div>
                        <input type="file"
                               name="signature_file"
                               id="signature_file"
                               class="form-control mt-2"
                               accept="image/png,image/webp,image/svg+xml">
                    </div>
                    <div class="mt-2">
                        <label class="form-label mb-1" for="signature_status">Estado de la firma</label>
                        <select name="signature_status" id="signature_status" class="form-select form-select-sm">
                            <option value="pending"      {{ ($user['signature_status'] ?? 'pending') === 'pending'      ? 'selected' : '' }}>Pendiente de revisión</option>
                            <option value="verified"     {{ ($user['signature_status'] ?? 'pending') === 'verified'     ? 'selected' : '' }}>Verificada</option>
                            <option value="not_provided" {{ ($user['signature_status'] ?? 'pending') === 'not_provided' ? 'selected' : '' }}>No proporcionada</option>
                        </select>
                        @if(!empty($validationErrors['signature_status']))
                            <div class="text-danger small">{{ $validationErrors['signature_status'] }}</div>
                        @endif
                    </div>
                </div>

                {{-- Sello + firma combinados --}}
                <div>
                    <p class="fw-semibold mb-2">Sello + firma combinados</p>
                    @if(!empty($user['seal_signature_url']))
                        <div class="mb-2">
                            <img src="{{ $user['seal_signature_url'] }}"
                                 alt="Sello y firma combinados"
                                 class="img-fluid border rounded"
                                 style="max-height:120px;">
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox"
                                   name="remove_seal_signature" id="remove_seal_signature" value="1">
                            <label class="form-check-label" for="remove_seal_signature">Eliminar imagen combinada actual</label>
                        </div>
                    @endif
                    <div class="drop-zone p-3 border rounded"
                         data-upload-drop-zone="seal_signature_file"
                         tabindex="0"
                         aria-label="Zona de carga para sello y firma combinados"
                         aria-describedby="seal_signature_help">
                        <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                            <div class="fw-semibold">Sello y firma en una sola imagen.</div>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-upload-trigger="seal_signature_file">
                                <i class="mdi mdi-upload"></i> Seleccionar
                            </button>
                        </div>
                        <div class="progress progress-xs mt-2 d-none"
                             data-upload-progress="seal_signature_file" aria-hidden="true">
                            <div class="progress-bar" role="progressbar" style="width:0%">0%</div>
                        </div>
                        <div class="text-danger small mt-2 {{ !empty($validationErrors['seal_signature_file']) ? '' : 'd-none' }}"
                             data-upload-error="seal_signature_file" role="alert">
                            {{ $validationErrors['seal_signature_file'] ?? '' }}
                        </div>
                        <div class="mt-2" data-upload-preview="seal_signature_file"></div>
                        <div class="text-muted small mt-2" id="seal_signature_help">PNG, WEBP o SVG · Máx 2 MB</div>
                        <input type="file"
                               name="seal_signature_file"
                               id="seal_signature_file"
                               class="form-control mt-2"
                               accept="image/png,image/webp,image/svg+xml">
                    </div>
                </div>

            </div>{{-- /form-upload-grid --}}

        </div>{{-- /tab-panel-documentos --}}

        {{-- ── Footer (persistente en todos los tabs) ──────────────────── --}}
        <div class="form-footer">
            @if(!$isCreate && !empty($user['id']) && !empty($canDelete))
                <button type="button"
                        class="btn btn-outline-danger form-delete-btn"
                        data-user-id="{{ (int) $user['id'] }}"
                        data-username="{{ $user['username'] ?? '' }}">
                    <i class="mdi mdi-alert-outline"></i> Eliminar usuario
                </button>
            @else
                <div><!-- spacer --></div>
            @endif
            <button type="submit" class="btn btn-primary">
                Guardar cambios
            </button>
        </div>

    </form>{{-- /form --}}

    {{-- ── Delete confirmation modal ───────────────────────────────────── --}}
    @if(!$isCreate && !empty($user['id']) && !empty($canDelete))
        <div id="delete-user-modal"
             class="modal-overlay"
             hidden
             role="dialog"
             aria-modal="true"
             aria-labelledby="delete-modal-title">
            <div class="modal-dialog-custom">
                <h5 id="delete-modal-title" class="mb-3">
                    <i class="mdi mdi-alert-outline text-danger"></i> Confirmar eliminación
                </h5>
                <p>
                    ¿Eliminar a
                    <strong>{{ $user['username'] ?? 'este usuario' }}</strong>?
                </p>
                <p class="text-muted small">Esta acción no se puede deshacer.</p>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button"
                            id="delete-modal-cancel"
                            class="btn btn-outline-secondary">
                        Cancelar
                    </button>
                    <form method="POST"
                          action="/usuarios/{{ (int) $user['id'] }}/delete">
                        @csrf
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- ── JS config block ─────────────────────────────────────────────── --}}
    <script>
    window.__USUARIOS_V2_EDIT__ = {
        permissionProfiles:   @json($permissionProfiles ?? []),
        rolesWithPermissions: @json($rolesWithPermissions),
        currentRoleId:        "{{ (string) ($user['role_id'] ?? '') }}",
        directPermissions:    @json($directPermissions),
    };
    </script>

</section>
@endsection

@push('scripts')
    <script src="/js/modules/user-media-upload.js"></script>
    @vite(['resources/css/usuarios.css', 'resources/js/v2/user-edit.js'])
@endpush

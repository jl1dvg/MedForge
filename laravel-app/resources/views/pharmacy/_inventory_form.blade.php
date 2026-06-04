@php
    $inv = $inv ?? null;
@endphp
<div class="row g-2">
    <div class="col-12">
        <label class="form-label">Nombre <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="nombre" value="{{ $inv?->nombre ?? '' }}" required>
    </div>
    <div class="col-12">
        <label class="form-label">Principio activo</label>
        <input type="text" class="form-control" name="principio_activo" value="{{ $inv?->principio_activo ?? '' }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Categoría <span class="text-danger">*</span></label>
        <select class="form-select" name="categoria" required>
            @foreach($categorias ?? [] as $cat)
                <option value="{{ $cat }}" @selected(($inv?->categoria ?? '') === $cat)>{{ ucfirst($cat) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Presentación</label>
        <input type="text" class="form-control" name="presentacion" value="{{ $inv?->presentacion ?? '' }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Stock actual</label>
        <input type="number" class="form-control" name="stock" min="0" value="{{ $inv?->stock ?? 0 }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Stock mínimo</label>
        <input type="number" class="form-control" name="stock_minimo" min="0" value="{{ $inv?->stock_minimo ?? 5 }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Precio</label>
        <input type="number" class="form-control" name="precio" min="0" step="0.01" value="{{ $inv?->precio ?? '' }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Estado</label>
        <select class="form-select" name="estado">
            <option value="activo" @selected(($inv?->estado ?? 'activo') === 'activo')>Activo</option>
            <option value="inactivo" @selected(($inv?->estado ?? '') === 'inactivo')>Inactivo</option>
        </select>
    </div>
</div>

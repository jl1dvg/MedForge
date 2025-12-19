<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Flujo de Pacientes</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Flujo de Pacientes</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<style>
    .kanban-toolbar {
        gap: 1rem;
    }
    #loader {
        display: none;
    }
</style>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Filtros del tablero</h4>
                </div>
                <div class="box-body">
                    <div class="row g-3 kanban-toolbar align-items-end">
                        <div class="col-md-3 col-sm-6">
                            <label for="kanbanDateFilter" class="form-label form-label-sm">Fecha de visita</label>
                            <input type="text" id="kanbanDateFilter" class="form-control" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label for="kanbanAfiliacionFilter" class="form-label form-label-sm">Afiliación</label>
                            <select id="kanbanAfiliacionFilter" class="form-select">
                                <option value="">Todas</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label for="kanbanDoctorFilter" class="form-label form-label-sm">Doctor</label>
                            <select id="kanbanDoctorFilter" class="form-select">
                                <option value="">Todos</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div id="loader" class="text-center my-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <div class="small text-muted mt-2">Cargando tablero</div>
            </div>

            <div id="kanban-summary" class="mb-3"></div>

            <div class="kanban-board kanban-board-wrapper bg-light p-3 d-flex flex-nowrap gap-3"></div>
        </div>
    </div>
</section>

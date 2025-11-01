<!-- result modal content -->
<div class="modal fade" id="resultModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="result-proyectado">Resultados</h4>
                <h4 class="modal-title" id="result-popup">Resultados</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row justify-content-between">
                    <div class="col-md-7 col-12">
                        <h4 id="test-name">Diagnóstico</h4>
                    </div>
                    <div class="col-md-5 col-12">
                        <h4 class="text-end" id="lab-order-id">Orden ID</h4>
                    </div>
                </div>
                <!-- Nueva tabla para Diagnósticos -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="bg-secondary">
                        <tr>
                            <th scope="col">CIE10</th>
                            <th scope="col">Detalle</th>
                        </tr>
                        </thead>
                        <tbody id="diagnostico-table">
                        <!-- Se llenará dinámicamente -->
                        </tbody>
                    </table>
                </div>
                <!-- Nueva tabla para Procedimientos -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="bg-secondary">
                        <tr>
                            <th scope="col">Código</th>
                            <th scope="col">Nombre del Procedimiento</th>
                        </tr>
                        </thead>
                        <tbody id="procedimientos-table">
                        <!-- Se llenará dinámicamente -->
                        </tbody>
                    </table>
                </div>

                <!-- Nueva tabla para mostrar fecha de inicio, hora de inicio, hora de fin, y duración -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="bg-secondary">
                        <tr>
                            <th>Fecha de Inicio</th>
                            <th>Hora de Inicio</th>
                            <th>Hora de Fin</th>
                            <th>Duración</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr id="timing-row">
                            <!-- Se llenará dinámicamente con 4 <td> -->
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="bg-secondary">
                        <tr>
                            <th scope="col" colspan="2">Procedimiento</th>
                        </tr>
                        </thead>
                        <tbody id="result-table">
                        <!-- Se llenará dinámicamente -->
                        </tbody>
                    </table>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="bg-secondary">
                        <tr>
                            <th scope="col" colspan="2">Staff Quirúrgico</th>
                        </tr>
                        </thead>
                        <tbody id="staff-table">
                        <!-- Se llenará dinámicamente -->
                        </tbody>
                    </table>
                </div>
                <div class="comment">
                    <p><span class="fw-600">Comentario</span> : <span class="comment-here text-mute"></span></p>
                </div>
                <!-- Agregar checkbox para marcar como revisado
<div class="form-check">
                    <input class="form-check-input" type="checkbox" id="markAsReviewed">
                    <label class="form-check-label" for="markAsReviewed">Marcar como revisado</label>
                </div> -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger pull-right" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-info pull-right" onclick="redirectToEditProtocol()">Revisar
                    Protocolo
                </button>
            </div>
        </div>
    </div>
</div>
<!-- /.modal-dialog -->
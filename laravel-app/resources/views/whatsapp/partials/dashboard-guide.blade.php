{{-- resources/views/whatsapp/partials/dashboard-guide.blade.php --}}
{{-- Guía interactiva del Dashboard WhatsApp — modal slide-through --}}

<button type="button"
        id="wa-guide-open"
        style="position:fixed;bottom:24px;right:24px;z-index:1050;border-radius:50px;padding:8px 18px;box-shadow:0 2px 8px rgba(0,0,0,.15);background:#fff;border:1px solid #e2e8f0;cursor:pointer;font-size:.85rem;color:#475569;font-weight:500;">
    📖 ¿Cómo usar este panel?
</button>

<div id="wa-guide-overlay"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1060;align-items:center;justify-content:center;">
    <div id="wa-guide-modal"
         style="background:#fff;border-radius:16px;width:min(680px,95vw);max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.22);">

        <div style="padding:20px 24px 16px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-size:.75rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Guía de uso</div>
                <div style="font-size:1.1rem;font-weight:700;color:#0f172a;">Panel de WhatsApp</div>
            </div>
            <button type="button" id="wa-guide-close"
                    style="background:none;border:none;cursor:pointer;font-size:1.4rem;color:#94a3b8;padding:4px;line-height:1;">✕</button>
        </div>

        <div id="wa-guide-slides" style="flex:1;overflow-y:auto;padding:28px 24px;">

            <div class="wa-guide-slide" data-slide="1">
                <div style="text-align:center;padding:12px 0 20px;">
                    <div style="font-size:3rem;">📊</div>
                    <h2 style="font-size:1.3rem;font-weight:700;margin:12px 0 8px;">¿Qué es este panel?</h2>
                    <p style="color:#475569;line-height:1.6;max-width:480px;margin:0 auto;">
                        Este panel te muestra <strong>cómo está funcionando el canal de WhatsApp</strong>: cuántos pacientes están escribiendo, cuántos fueron atendidos, cuántas citas se generaron y cómo está respondiendo el equipo.
                    </p>
                </div>
                <div style="background:#f8fafc;border-radius:10px;padding:16px 20px;margin-top:12px;">
                    <div style="font-weight:600;margin-bottom:8px;color:#0f172a;">El panel tiene 3 partes:</div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <span style="font-size:1.4rem;">🚦</span>
                            <div><strong>Lo que pasa ahora</strong> — números en tiempo real con colores de alerta</div>
                        </div>
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <span style="font-size:1.4rem;">📈</span>
                            <div><strong>Rendimiento del periodo</strong> — análisis del rango de fechas seleccionado</div>
                        </div>
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <span style="font-size:1.4rem;">🔍</span>
                            <div><strong>Detalle</strong> — datos por agente, por anuncio (se abre con clic en el título)</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wa-guide-slide" data-slide="2" style="display:none;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <span style="font-size:2rem;">🚦</span>
                    <h2 style="font-size:1.2rem;font-weight:700;margin:0;">Lo que pasa ahora</h2>
                </div>
                <p style="color:#475569;line-height:1.6;margin-bottom:16px;">
                    Los 4 números superiores se actualizan según el rango de fechas. Los colores te avisan si hay algo que atender:
                </p>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div style="border-left:4px solid #ef4444;background:#fff5f5;padding:12px 16px;border-radius:6px;">
                        <strong style="color:#dc2626;">Rojo</strong> — Situación crítica que necesita atención inmediata
                    </div>
                    <div style="border-left:4px solid #f59e0b;background:#fffbeb;padding:12px 16px;border-radius:6px;">
                        <strong style="color:#d97706;">Amarillo</strong> — Situación a monitorear, no urgente
                    </div>
                    <div style="border-left:4px solid #10b981;background:#f0fdf4;padding:12px 16px;border-radius:6px;">
                        <strong style="color:#059669;">Verde</strong> — Todo dentro de lo normal
                    </div>
                </div>
                <div style="background:#f8fafc;border-radius:10px;padding:16px 20px;margin-top:16px;">
                    <div style="font-weight:600;margin-bottom:10px;">¿Qué significa cada número?</div>
                    <div style="display:flex;flex-direction:column;gap:8px;font-size:.9rem;color:#475569;">
                        <div><strong style="color:#0f172a;">En espera ahora:</strong> Conversaciones sin asignar a ningún agente.</div>
                        <div><strong style="color:#0f172a;">Sin atender:</strong> Pacientes que escribieron y no recibieron respuesta humana.</div>
                        <div><strong style="color:#0f172a;">De cada 10 que escriben:</strong> % de pacientes que sí recibieron respuesta.</div>
                        <div><strong style="color:#0f172a;">Respondidos a tiempo:</strong> Cuántos recibieron respuesta dentro del tiempo objetivo.</div>
                    </div>
                </div>
            </div>

            <div class="wa-guide-slide" data-slide="3" style="display:none;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <span style="font-size:2rem;">🔎</span>
                    <h2 style="font-size:1.2rem;font-weight:700;margin:0;">Cómo usar los filtros</h2>
                </div>
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div style="border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;">
                        <div style="font-weight:600;margin-bottom:4px;">📅 Rango de fechas</div>
                        <div style="color:#475569;font-size:.9rem;">Filtra todos los datos al periodo seleccionado. Por defecto muestra los últimos 30 días.</div>
                    </div>
                    <div style="border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;">
                        <div style="font-weight:600;margin-bottom:4px;">👤 Agente</div>
                        <div style="color:#475569;font-size:.9rem;">Muestra solo los datos del agente seleccionado, incluyendo conversaciones que ya no tiene asignadas pero que atendió en el periodo.</div>
                    </div>
                    <div style="border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;">
                        <div style="font-weight:600;margin-bottom:4px;">⏱ Tiempo objetivo de respuesta</div>
                        <div style="color:#475569;font-size:.9rem;">Define en minutos cuánto tiempo máximo debería tardar el equipo. El porcentaje "respondidos a tiempo" se calcula contra este valor. Por defecto: 15 minutos.</div>
                    </div>
                </div>
            </div>

            <div class="wa-guide-slide" data-slide="4" style="display:none;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <span style="font-size:2rem;">📣</span>
                    <h2 style="font-size:1.2rem;font-weight:700;margin:0;">Tabla de anuncios</h2>
                </div>
                <p style="color:#475569;line-height:1.6;margin-bottom:16px;">
                    Muestra qué anuncios de Facebook e Instagram están generando conversaciones y citas en WhatsApp.
                </p>
                <div style="display:flex;flex-direction:column;gap:10px;font-size:.9rem;">
                    <div style="display:flex;gap:10px;">
                        <span style="min-width:120px;font-weight:600;color:#0f172a;">Anuncio:</span>
                        <span style="color:#475569;">Nombre del anuncio tal como aparece en Meta Ads Manager.</span>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <span style="min-width:120px;font-weight:600;color:#0f172a;">Red social:</span>
                        <span style="color:#475569;">Si el paciente llegó desde Facebook (📘) o Instagram (📷).</span>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <span style="min-width:120px;font-weight:600;color:#0f172a;">Conversaciones:</span>
                        <span style="color:#475569;">Cuántas personas llegaron por ese anuncio.</span>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <span style="min-width:120px;font-weight:600;color:#0f172a;">Identificadas:</span>
                        <span style="color:#475569;">Cuántas ya tenían historia clínica (pacientes conocidos).</span>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <span style="min-width:120px;font-weight:600;color:#0f172a;">Citas (%):</span>
                        <span style="color:#475569;">Cuántas terminaron agendando. El % muestra la efectividad del anuncio.</span>
                    </div>
                </div>
                <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:12px 16px;margin-top:16px;font-size:.85rem;color:#92400e;">
                    💡 <strong>Tip:</strong> Un anuncio con muchas conversaciones pero pocas citas puede indicar que el flujo del bot necesita ajuste.
                </div>
            </div>

            <div class="wa-guide-slide" data-slide="5" style="display:none;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <span style="font-size:2rem;">👥</span>
                    <h2 style="font-size:1.2rem;font-weight:700;margin:0;">Estadísticas por agente</h2>
                </div>
                <p style="color:#475569;line-height:1.6;margin-bottom:16px;">
                    Las secciones de detalle (al final, se abren haciendo clic en el título) muestran el rendimiento individual.
                </p>
                <div style="display:flex;flex-direction:column;gap:10px;font-size:.9rem;">
                    <div style="border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;">
                        <div style="font-weight:600;margin-bottom:4px;">Conversaciones atendidas</div>
                        <div style="color:#475569;">Cuántas conversaciones recibieron al menos un mensaje del agente.</div>
                    </div>
                    <div style="border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;">
                        <div style="font-weight:600;margin-bottom:4px;">Tiempo promedio de primera respuesta</div>
                        <div style="color:#475569;">Minutos que tardó el agente en responder desde la asignación.</div>
                    </div>
                    <div style="border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;">
                        <div style="font-weight:600;margin-bottom:4px;">Derivaciones asignadas / resueltas</div>
                        <div style="color:#475569;">Cuántas conversaciones recibió y cuántas marcó como resueltas.</div>
                    </div>
                </div>
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;margin-top:16px;font-size:.85rem;color:#1e40af;">
                    💡 <strong>Nota:</strong> Usa el filtro de agente arriba para ver TODOS los KPIs filtrados por una persona específica.
                </div>
            </div>

        </div>

        <div style="padding:16px 24px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;gap:8px;" id="wa-guide-dots">
                @for($i = 1; $i <= 5; $i++)
                    <button class="wa-guide-dot" data-target="{{ $i }}"
                            style="width:8px;height:8px;border-radius:50%;border:none;cursor:pointer;background:{{ $i === 1 ? '#3b82f6' : '#cbd5e1' }};padding:0;transition:background .2s;"></button>
                @endfor
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" id="wa-guide-prev"
                        class="btn btn-sm btn-light" style="display:none;">← Anterior</button>
                <button type="button" id="wa-guide-next"
                        class="btn btn-sm btn-primary">Siguiente →</button>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    var TOTAL   = 5;
    var current = 1;
    var overlay = document.getElementById('wa-guide-overlay');
    var btnOpen  = document.getElementById('wa-guide-open');
    var btnClose = document.getElementById('wa-guide-close');
    var btnNext  = document.getElementById('wa-guide-next');
    var btnPrev  = document.getElementById('wa-guide-prev');

    function showSlide(n) {
        document.querySelectorAll('.wa-guide-slide').forEach(function (s) {
            s.style.display = 'none';
        });
        var slide = document.querySelector('[data-slide="' + n + '"]');
        if (slide) slide.style.display = 'block';

        document.querySelectorAll('.wa-guide-dot').forEach(function (d) {
            d.style.background = parseInt(d.dataset.target) === n ? '#3b82f6' : '#cbd5e1';
        });

        btnPrev.style.display = n > 1 ? 'inline-block' : 'none';
        btnNext.textContent   = n < TOTAL ? 'Siguiente →' : 'Cerrar';
        current = n;
    }

    btnOpen.addEventListener('click', function () {
        overlay.style.display = 'flex';
        showSlide(1);
    });

    btnClose.addEventListener('click', function () {
        overlay.style.display = 'none';
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) overlay.style.display = 'none';
    });

    btnNext.addEventListener('click', function () {
        if (current < TOTAL) showSlide(current + 1);
        else overlay.style.display = 'none';
    });

    btnPrev.addEventListener('click', function () {
        if (current > 1) showSlide(current - 1);
    });

    document.querySelectorAll('.wa-guide-dot').forEach(function (d) {
        d.addEventListener('click', function () {
            showSlide(parseInt(this.dataset.target));
        });
    });
}());
</script>

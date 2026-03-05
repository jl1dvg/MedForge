### Orden recomendado
1. Fecha
2. Doctor o Afiliación
3. Responsable CRM
4. Buscar texto
5. Derivación vencida/por vencer

### Buscar (`#kanbanSearchFilter`)
- Tipo: Local
- Cuándo usarlo: cuando el tablero ya está cargado y necesitas ubicar paciente/HC rápido.
- Qué debería pasar: filtra tarjetas visibles sin recargar.
- Error común: buscar antes de aplicar un rango de fecha útil.

### Doctor (`#kanbanDoctorFilter`)
- Tipo: Servidor
- Cuándo usarlo: para distribuir trabajo por médico.
- Qué debería pasar: recarga con solicitudes del doctor elegido.
- Error común: pensar que es filtro local instantáneo.

### Fecha (`#kanbanDateFilter`)
- Tipo: Servidor
- Cuándo usarlo: al inicio del turno para acotar volumen.
- Qué debería pasar: solo muestra solicitudes del rango seleccionado.
- Error común: no aplicar rango y asumir que faltan casos.

### Afiliación (`#kanbanAfiliacionFilter`)
- Tipo: Servidor
- Cuándo usarlo: para priorizar cobertura/aseguradora.
- Qué debería pasar: tablero filtrado por afiliación.
- Error común: combinar demasiados filtros y quedarse en 0 resultados.

### Tipo solicitud (`#kanbanTipoFilter`)
- Tipo: Local
- Cuándo usarlo: para separar CIRUGÍA y PROCEDIMIENTO.
- Qué debería pasar: filtra tarjetas cargadas por tipo.
- Error común: esperar datos que no están cargados.

### Derivación vencida / por vencer
- Tipo: Local
- IDs: `#kanbanDerivacionVencidaFilter`, `#kanbanDerivacionPorVencerFilter`, `#kanbanDerivacionDiasInput`
- Cuándo usarlo: para priorizar riesgo documental.
- Qué debería pasar: muestra solo casos vencidos o por vencer según días.
- Error común: no ajustar días por vencer.

### Responsable CRM (`#kanbanResponsableFilter`)
- Tipo: Servidor
- Cuándo usarlo: para seguimiento por dueño de caso.
- Qué debería pasar: recarga con solicitudes del responsable elegido.
- Error común: olvidar limpiar el filtro al terminar.

### Atajo sin responsable (`#kanbanCrmSinResponsableFilter`)
- Tipo: Local
- Cuándo usarlo: para rescatar casos huérfanos al inicio del turno.
- Qué debería pasar: deja visibles solo tarjetas sin responsable CRM.
- Error común: confundirlo con el filtro servidor "Sin responsable".

#!/bin/bash
FECHA_INICIO=$(date +%F)
FECHA_FIN=$(date -d "$FECHA_INICIO +15 days" +%F)

php /homepages/26/d793096920/htdocs/cive/public/fix/agenda.php start=$FECHA_INICIO end=$FECHA_FIN > /homepages/26/d793096920/htdocs/cive/public/fix/logs/agenda_$FECHA_INICIO.log 2>&1
import React, { useState } from 'react';
import { CatalogContext, useToast } from './kit';
import { ProtocolList } from './list';
import { ProtocolWizard } from './wizard';
import { createApi } from './api';

export function ProtocolosApp({ config }) {
  const route = config.route || { name: 'list' };
  const endpoints = config.endpoints || {};
  const catalogos = config.catalogos || {};
  const api = createApi(endpoints);

  const [protocolos, setProtocolos] = useState(() => config.catalogo || []);
  const [toastNode, showToast] = useToast();

  const goTo = (url) => { window.location.href = url; };

  const openEdit = (id) => goTo(`${endpoints.editar}?id=${encodeURIComponent(id)}`);
  const openNew = () => goTo(endpoints.nuevo);
  const duplicate = (p) => goTo(`${endpoints.editar}?duplicar=${encodeURIComponent(p.id)}`);
  const remove = async (p) => {
    const res = await api.eliminar(p.id);
    if (res.ok && res.success) {
      setProtocolos((arr) => arr.filter((x) => x.id !== p.id));
      showToast('Protocolo eliminado.');
    } else {
      showToast(res.message || 'No se pudo eliminar el protocolo.');
    }
  };

  return (
    <CatalogContext.Provider value={catalogos}>
      {route.name === 'list' && (
        <ProtocolList
          protocolos={protocolos}
          onOpen={openEdit}
          onNew={openNew}
          onDuplicate={duplicate}
          onDelete={remove}
          canManage={!!config.canManage}
          toastNode={toastNode}
          errorMessage={route.error}
        />
      )}
      {(route.name === 'new' || route.name === 'edit') && (
        <ProtocolWizard
          mode={route.name}
          initial={route.protocolo || null}
          duplicandoDe={route.duplicandoDe || null}
          api={api}
          onExit={() => goTo(endpoints.catalogo)}
          onSave={() => {}}
        />
      )}
    </CatalogContext.Provider>
  );
}

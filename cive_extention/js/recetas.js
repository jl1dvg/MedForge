function ejecutarRecetaEnPagina(item) {
    // Evitar ejecuciones múltiples del mismo botón
    if (window.recetaEjecutando) {
        console.warn("Ya se está ejecutando una receta. Ignorando clic repetido.");
        return;
    }
    window.recetaEjecutando = true;
// Verificar que el item tenga la estructura esperada
    console.log('Item recibido en ejecutarProtocoloEnPagina:', item);

    if (!item || typeof item !== 'object') {
        console.error('El item recibido no tiene la estructura esperada.', item);
        return;
    }

    // Asegúrate de que el DOM esté listo antes de hacer     cualquier operación
    function llenarCampoTexto(selector, valor) {
        return new Promise((resolve, reject) => {
            const textArea = document.querySelector(selector);
            if (textArea) {
                console.log(`Llenando el campo de texto "${selector}" con "${valor}"`);
                textArea.value = valor;
                setTimeout(resolve, 100); // Añadir un retraso para asegurar que el valor se establezca
            } else {
                console.error(`El campo de texto "${selector}" no se encontró.`);
                reject(`El campo de texto "${selector}" no se encontró.`);
            }
        });
    }

    function hacerClickEnBoton(selector, veces) {
        return new Promise((resolve, reject) => {
            const clickear = (i) => {
                if (i >= veces) {
                    resolve();
                    return;
                }

                const boton = document.querySelector(selector);
                if (boton) {
                    console.log(`Haciendo clic en el botón "${selector}" (${i + 1}/${veces})`);
                    boton.click();
                    setTimeout(() => clickear(i + 1), 500);
                } else {
                    console.error(`No se encontró el botón: ${selector}`);
                    reject(`No se encontró el botón: ${selector}`);
                }
            };

            clickear(0);
        });
    }

    function hacerClickEnSelect2(selector) {
        return new Promise((resolve, reject) => {
            const container = document.querySelector(selector);
            if (!container) {
                console.error(`El contenedor "${selector}" no se encontró.`);
                reject(`El contenedor "${selector}" no se encontró.`);
                return;
            }

            console.log(`Haciendo clic en el contenedor: ${selector}`);
            const event = new MouseEvent('mousedown', {view: window, bubbles: true, cancelable: true});
            container.dispatchEvent(event);

            let retries = 0;
            const maxRetries = 10;

            const checkField = () => {
                const field = document.querySelector('input.select2-search__field');
                if (field) {
                    resolve();
                } else if (retries < maxRetries) {
                    retries++;
                    setTimeout(checkField, 300);
                } else {
                    console.error('No se encontró el campo de búsqueda tras hacer click.');
                    reject('No se encontró el campo de búsqueda.');
                }
            };

            setTimeout(checkField, 200);
        });
    }

    function establecerBusqueda(selector, valor) {
        return new Promise((resolve, reject) => {
            let attempts = 0;
            const maxAttempts = 20;

            const searchForField = () => {
                const searchField = document.querySelector('input.select2-search__field');
                if (!searchField) {
                    console.log(`Intento ${attempts + 1}: no se encontró el campo de búsqueda. Retentando...`);
                    attempts++;
                    if (attempts < maxAttempts) {
                        hacerClickEnSelect2(selector).then(() => setTimeout(searchForField, 500)).catch(error => reject(error));
                    } else {
                        console.error('El campo de búsqueda del Select2 no se encontró.');
                        reject('El campo de búsqueda del Select2 no se encontró.');
                    }
                } else {
                    console.log('Estableciendo búsqueda:', valor);
                    searchField.value = valor;
                    const inputEvent = new Event('input', {bubbles: true, cancelable: true});
                    searchField.dispatchEvent(inputEvent);
                    setTimeout(() => resolve(searchField), 500);
                }
            };

            searchForField();
        });
    }

    function seleccionarOpcion() {
        return new Promise((resolve, reject) => {
            // Verificar que el selector existe y es accesible antes de intentar realizar operaciones
            if (document.querySelector('input.select2-search__field') === null) {
                console.error('El campo de búsqueda select2-search__field no existe en el DOM.');
                return;
            }
            const searchField = document.querySelector('input.select2-search__field');
            if (searchField) {
                console.log('Seleccionando opción');
                const enterEvent = new KeyboardEvent('keydown', {
                    key: 'Enter', keyCode: 13, bubbles: true, cancelable: true
                });
                searchField.dispatchEvent(enterEvent);
                setTimeout(resolve, 200); // Añadir un retraso para asegurar que la opción se seleccione
            } else {
                console.error('El campo de búsqueda del Select2 no se encontró para seleccionar la opción.');
                reject('El campo de búsqueda del Select2 no se encontró para seleccionar la opción.');
            }
        });
    }

    function llenarCampoCantidad(selector, cantidad, tabCount = 0) {
        return new Promise((resolve, reject) => {
            const campoCantidad = document.querySelector(selector);
            if (campoCantidad) {
                console.log(`Llenando el campo cantidad con el valor: ${cantidad}`);
                campoCantidad.focus();
                campoCantidad.value = cantidad;
                campoCantidad.dispatchEvent(new Event('input', {bubbles: true}));
                campoCantidad.dispatchEvent(new Event('change', {bubbles: true}));

                // Simular la tecla TAB la cantidad de veces especificada
                let tabsPressed = 0;
                const pressTab = () => {
                    if (tabsPressed < tabCount) {
                        const tabEvent = new KeyboardEvent('keydown', {
                            key: 'Tab',
                            keyCode: 9,
                            code: 'Tab',
                            which: 9,
                            bubbles: true,
                            cancelable: true
                        });
                        document.activeElement.dispatchEvent(tabEvent);

                        const tabEventPress = new KeyboardEvent('keypress', {
                            key: 'Tab',
                            keyCode: 9,
                            code: 'Tab',
                            which: 9,
                            bubbles: true,
                            cancelable: true
                        });
                        document.activeElement.dispatchEvent(tabEventPress);

                        const tabEventUp = new KeyboardEvent('keyup', {
                            key: 'Tab',
                            keyCode: 9,
                            code: 'Tab',
                            which: 9,
                            bubbles: true,
                            cancelable: true
                        });
                        document.activeElement.dispatchEvent(tabEventUp);

                        tabsPressed++;
                        setTimeout(pressTab, 100); // Asegurar que el evento se despacha correctamente
                    } else {
                        campoCantidad.blur();
                        resolve();
                    }
                };
                pressTab();
            } else {
                console.error('El campo cantidad no se encontró.');
                reject('El campo cantidad no se encontró.');
            }
        });
    }


    function obtenerOjoATratar() {
        // Función auxiliar para buscar texto en un contenedor y retornar las siglas y descripción del ojo
        function buscarOjoEnTexto(texto) {
            const textoUpper = texto.toUpperCase();
            if (textoUpper.includes("OJO DERECHO")) {
                return {sigla: "OD", descripcion: "OJO DERECHO"};
            } else if (textoUpper.includes("OJO IZQUIERDO")) {
                return {sigla: "OI", descripcion: "OJO IZQUIERDO"};
            } else if (textoUpper.includes("AMBOS OJOS")) {
                return {sigla: "AO", descripcion: "AMBOS OJOS"};
            } else {
                return null; // Retorna null si no encuentra nada
            }
        }

        // Buscar en las notas del doctor primero
        const notasDoctorElement = document.querySelector("div[style*='text-align:justify;'] div[style*='margin-right:30px;margin-left:30px;']");
        if (notasDoctorElement) {
            const resultado = buscarOjoEnTexto(notasDoctorElement.textContent);
            if (resultado) {
                return resultado;
            }
        }

        // Si no se encuentra en las notas del doctor, buscar en el pedido de cirugía
        const liElement = Array.from(document.querySelectorAll('li')).find(li => li.textContent.includes("PEDIDO DE CIRUGÍA"));
        if (liElement) {
            const seccionQuirurgica = Array.from(liElement.querySelectorAll('b')).find(b => b.textContent.includes("SECCIÓN:"));
            if (seccionQuirurgica) {
                const resultado = buscarOjoEnTexto(seccionQuirurgica.nextSibling.textContent);
                if (resultado) {
                    return resultado;
                }
            }
        }

        // Si no se encuentra en ninguno de los dos lugares, retornar ojo no especificado
        return {sigla: "", descripcion: "Ojo no especificado"};
    }

// Ejecutar y mostrar el resultado
    const ojoATratar = obtenerOjoATratar();
    console.log(ojoATratar);

    function ejecutarRecetas(item) {
        if (!Array.isArray(item.recetas)) return Promise.resolve();

        // Validación de consistencia entre recetaCount y recetas.length
        const totalEsperado = item.recetaCount + 1;
        const totalRecetas = item.recetas.length;
        if (totalRecetas < totalEsperado) {
            console.warn(`⚠️ El JSON contiene solo ${totalRecetas} recetas, pero se esperaban ${totalEsperado} según recetaCount.`);
            Swal.fire({
                icon: 'warning',
                title: 'Inconsistencia en receta',
                text: `Solo hay ${totalRecetas} recetas pero se esperaban ${totalEsperado}. Verifica el JSON.`,
                timer: 5000
            });
        }

        return hacerClickEnBoton('#prescripcion', 1)
            .then(() => hacerClickEnBoton('#recetas-input .list-cell__button .js-input-plus', item.recetas.length - 1))
            .then(() => esperarElemento(`#select2-recetas-recetasadd-0-producto_id-container`)) // Solo se ejecuta una vez
            .then(() => {
                // Iterar sobre cada receta
                return item.recetas.reduce((promise, receta) => {
                    return promise.then(() => {
                        // Manejar el producto
                        return hacerClickEnSelect2(`#select2-recetas-recetasadd-${receta.id}-producto_id-container`)
                            .then(() => establecerBusqueda(`#select2-recetas-recetasadd-${receta.id}-producto_id-container`, receta.nombre))
                            .then(() => seleccionarOpcion());
                    });
                }, Promise.resolve()); // Inicializa con una promesa resuelta
            })
            .then(() => {
                // Ahora manejar las vías
                return item.recetas.reduce((promise, receta) => {
                    return promise.then(() => {
                        return hacerClickEnSelect2(`#select2-recetas-recetasadd-${receta.id}-vias-container`)
                            .then(() => establecerBusqueda(`#select2-recetas-recetasadd-${receta.id}-vias-container`, receta.via))
                            .then(() => seleccionarOpcion());
                    });
                }, Promise.resolve()); // Inicializa con una promesa resuelta
            })
            .then(() => {
                // Ahora manejar las unidades
                return item.recetas.reduce((promise, receta) => {
                    return promise.then(() => {
                        return hacerClickEnSelect2(`#select2-recetas-recetasadd-${receta.id}-unidad_id-container`)
                            .then(() => establecerBusqueda(`#select2-recetas-recetasadd-${receta.id}-unidad_id-container`, receta.unidad))
                            .then(() => seleccionarOpcion());
                    });
                }, Promise.resolve()); // Inicializa con una promesa resuelta
            })
            .then(() => {
                // Ahora manejar las pautas
                return item.recetas.reduce((promise, receta) => {
                    return promise.then(() => {
                        return hacerClickEnSelect2(`#select2-recetas-recetasadd-${receta.id}-pauta-container`)
                            .then(() => establecerBusqueda(`#select2-recetas-recetasadd-${receta.id}-pauta-container`, receta.pauta))
                            .then(() => seleccionarOpcion());
                    });
                }, Promise.resolve()); // Inicializa con una promesa resuelta
            })
            .then(() => {
                // Ahora manejar las cantidades
                return item.recetas.reduce((promise, receta) => {
                    return promise.then(() => {
                        return llenarCampoCantidad(`#recetas-recetasadd-${receta.id}-cantidad`, receta.cantidad, 2)
                    });
                }, Promise.resolve()); // Inicializa con una promesa resuelta
            })
            .then(() => {
                // Ahora manejar las total_farmacia
                return item.recetas.reduce((promise, receta) => {
                    return promise.then(() => {
                        return llenarCampoTexto(`#recetas-recetasadd-${receta.id}-total_farmacia`, receta.totalFarmacia)
                    });
                }, Promise.resolve()); // Inicializa con una promesa resuelta
            })
            .then(() => {
                // Ahora manejar las observaciones
                return item.recetas.reduce((promise, receta) => {
                    return promise.then(() => {
                        return llenarCampoTexto(`#recetas-recetasadd-${receta.id}-observaciones`, receta.observaciones)
                    });
                }, Promise.resolve()); // Inicializa con una promesa resuelta
            });
    }

    function esperarElemento(selector) {
        return new Promise((resolve, reject) => {
            const elemento = document.querySelector(selector);
            if (elemento) {
                resolve(elemento);
                return;
            }

            const observer = new MutationObserver((mutations, observerInstance) => {
                mutations.forEach((mutation) => {
                    const nodes = Array.from(mutation.addedNodes);
                    for (const node of nodes) {
                        if (node.matches && node.matches(selector)) {
                            observerInstance.disconnect();
                            resolve(node);
                            return;
                        }
                    }
                });
            });

            observer.observe(document.body, {
                childList: true, subtree: true
            });

            setTimeout(() => {
                observer.disconnect();
                reject(`El elemento "${selector}" no se encontró dentro del tiempo esperado.`);
            }, 10000); // Timeout de 10 segundos, ajusta según sea necesario
        });
    }


    ejecutarRecetas(item)
        .then(() => {
            window.recetaEjecutando = false;
        })
        .catch(error => {
            console.error('Error en la ejecución de acciones:', error);
            window.recetaEjecutando = false;
        });
}




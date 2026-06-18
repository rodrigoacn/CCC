$(document).ready(function(){
    $('#input-3').rating({displayOnly: true, step: 0.5});
    $('#input-5').rating({clearCaption: 'No stars yet'});
    $('#input-8').rating({rtl: true, containerClass: 'is-star'});
    $('#input-9').rating();
});

$(document).ready(function() {
    $('#input-busqueda').on('keyup', function() {
        var valorBusqueda = $(this).val()
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "");

        // Itera por cada tabla de Bootstrap de forma independiente
        $('.table').each(function() {
            var $tabla = $(this);
            var filasVisibles = 0;

            // Filtra las filas del cuerpo de la tabla actual
            $tabla.find('tbody tr').each(function() {
                var textoFila = $(this).text()
                    .toLowerCase()
                    .normalize("NFD")
                    .replace(/[\u0300-\u036f]/g, "");

                if (textoFila.indexOf(valorBusqueda) > -1) {
                    $(this).show();
                    filasVisibles++; // Cuenta la fila si coincide
                } else {
                    $(this).hide();
                }
            });

            // Muestra u oculta la tabla completa según el contador
            if (filasVisibles > 0) {
                $tabla.show();
            } else {
                $tabla.hide();
            }
        });
    });
});

$(document).ready(function() {
    
    // =================================================================
    // 1. MOTOR DINÁMICO: CONSTRUCTOR DE TABLAS Y RENDIMIENTO PORCENTUAL
    // =================================================================
    function cargarMateria(llaveMateria) {
        var datos = baseDatosTemarios[llaveMateria];
        if (!datos) return;

        // Actualización de textos base e interfaces limpias
        $('#titulo-materia').text(datos.titulo);
        $('#input-busqueda').val('');
        $('#alerta-no-resultados').addClass('d-none');
        
        var $contenedor = $('#contenedor-tablas').empty();

        // Construcción estructurada de tablas iterativas
        datos.tablas.forEach(function(tabla, indexTabla) {
            var htmlTabla = `
              <table class="table table-dark table-striped table-hover caption-top border border-secondary mb-5">
                <caption>${tabla.caption}</caption>
                <thead>
                  <tr>
                    <th scope="col" style="width: 10%; text-align: center;">Choose</th>
                    <th scope="col" style="width: 25%;">Theme</th>
                    <th scope="col" style="width: 65%;">Description</th>
                  </tr>
                </thead>
                <tbody>
            `;

            tabla.filas.forEach(function(filas, indexFila) {
                var idUnico = `${llaveMateria}_${indexTabla}_${indexFila}`;
                
                // Extracción de logs persistentes en LocalStorage
                var estadoGuardado = localStorage.getItem(idUnico) === "true";
                var claseCompletada = estadoGuardado ? "fila-completada" : "";
                var checkAtributo = estadoGuardado ? "checked" : "";

                htmlTabla += `
                  <tr class="${claseCompletada}">
                    <td style="text-align: center; vertical-align: middle;">
                        <input class="form-check-input check-progreso" type="checkbox" id="${idUnico}" ${checkAtributo}>
                    </td>
                    <td class="fw-bold"><label class="form-check-label w-100" for="${idUnico}">${filas.tema}</label></td>
                    <td><label class="form-check-label w-100 text-white-50" for="${idUnico}">${filas.desc}</label></td>
                  </tr>
                `;
            });

            htmlTabla += `</tbody></table>`;
            $contenedor.append(htmlTabla);
        });

        // Ejecuta el cálculo métrico inicial de la materia cargada
        actualizarMétricaProgreso();
    }

    // =================================================================
    // 2. SISTEMA MÉTRICO: CÁLCULO DE PORCENTAJE EN TIEMPO REAL
    // =================================================================
    function actualizarMétricaProgreso() {
        var totalCheckboxes = $('.check-progreso').length;
        var marcados = $('.check-progreso:checked').length;
        var porcentaje = 0;

        if (totalCheckboxes > 0) {
            porcentaje = Math.round((marcados / totalCheckboxes) * 100);
        }

        // Remueve indicadores antiguos si existen para evitar duplicados en el DOM
        $('#marcador-porcentaje').remove();

        // Inyecta dinámicamente un cintillo de progreso estilizado bajo el subtítulo h2
        var htmlMétrica = `
            <div id="marcador-porcentaje" class="mb-4 p-3 bg-secondary bg-opacity-10 border border-secondary rounded d-flex align-items-center justify-content-between">
                <div>
                    <span class="fw-bold text-primary">Syllabus Progress:</span> 
                    <span class="text-white-50">${marcados} of ${totalCheckboxes} themes completed</span>
                </div>
                <div class="h3 mb-0 fw-bold text-success">${porcentaje}%</div>
            </div>
        `;
        
        $('h2.text-secondary').after(htmlMétrica);
    }

    // Carga automatizada inicial (Materia: Geografía)
    cargarMateria('media-geography');

    // =================================================================
    // 3. LISTENERS & CONTROLADORES DE EVENTOS
    // =================================================================

    // MANEJADOR 1: Guardado persistente inmediato al hacer clic en un checkbox
    $('#contenedor-tablas').on('change', '.check-progreso', function() {
        var idCheck = $(this).attr('id');
        var estaMarcado = $(this).is(':checked');
        
        localStorage.setItem(idCheck, estaMarcado);
        
        if (estaMarcado) {
            $(this).closest('tr').addClass('fila-completada');
        } else {
            $(this).closest('tr').removeClass('fila-completada');
        }

        // Actualiza el porcentaje en tiempo real sin recargar la tabla
        actualizarMétricaProgreso();
    });

    // MANEJADOR 2: Limpieza absoluta del almacenamiento local con confirmación segura
    $('#btn-limpiar-progreso').on('click', function() {
        if (confirm("Are you sure you want to clear your entire study progress logs? This action cannot be reversed.")) {
            localStorage.clear();
            var materiaActiva = $('.sidebar .list-group-item.active').data('materia');
            cargarMateria(materiaActiva);
        }
    });

    // MANEJADOR 3: Conmutador del menú lateral para alternar entre las 24 asignaturas
    $('.sidebar .list-group-item').on('click', function() {
        $('.sidebar .list-group-item').removeClass('active');
        $(this).addClass('active');
        var materiaSeleccionada = $(this).data('materia');
        cargarMateria(materiaSeleccionada);
    });

    // MANEJADOR 4: Algoritmo de filtrado avanzado y supresión de elementos vacíos
    $('#input-busqueda').on('keyup', function() {
        var valorBusqueda = $(this).val().toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        var tablasVisiblesTotales = 0;

        $('.table').each(function() {
            var $tabla = $(this);
            var filasVisibles = 0;

            $tabla.find('tbody tr').each(function() {
                var textoFila = $(this).text().toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

                if (textoFila.indexOf(valorBusqueda) > -1) {
                    $(this).show();
                    filasVisibles++;
                } else {
                    $(this).hide();
                }
            });

            // Oculta o muestra la tabla completa basándose en la presencia de filas válidas
            if (filasVisibles > 0) {
                $tabla.show();
                tablasVisiblesTotales++;
            } else {
                $tabla.hide();
            }
        });

        // Despliega o remueve el contenedor de advertencia por falta de coincidencias
        if (tablasVisiblesTotales === 0 && valorBusqueda !== "") {
            $('#alerta-no-resultados').removeClass('d-none');
        } else {
            $('#alerta-no-resultados').addClass('d-none');
        }
    });
});

$(document).ready(function() {
    $('.timer').each(function() {
        const $timerDisplay = $(this);
        
        // Busca la etiqueta .timer-label que pertenece ÚNICAMENTE a este reloj
        const $timerLabel = $timerDisplay.closest('.text-center').find('.timer-label');
        
        // Extrae el tiempo del div y lo divide por los dos puntos
        const timeText = $timerDisplay.text().trim();
        const timeParts = timeText.split(':');
        
        // Convierte a segundos usando las posiciones exactas del arreglo [0] y [1]
        let minutes = parseInt(timeParts[0], 10) || 0;
        let seconds = parseInt(timeParts[1], 10) || 0;
        let totalSeconds = (minutes * 60) + seconds;
        
        let isOvertime = false;

        function formatTime(secs) {
            let absSeconds = Math.abs(secs);
            let m = Math.floor(absSeconds / 60);
            let s = absSeconds % 60;
            return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }

        const interval = setInterval(function() {
            if (!isOvertime) {
                totalSeconds--;
                
                // Al llegar a cero la cuenta regresiva
                if (totalSeconds <= 0) {
                    isOvertime = true;
                    totalSeconds = 0; 
                    
                    // Modifica la frase del reloj actual sin tocar los demás
                    $timerLabel.text('Clase empezó hace:');
                    
                    // Cambia las clases de Bootstrap (quita el blanco/azul y pone rojo de alerta)
                    $timerLabel.removeClass('text-white').addClass('text-danger');
                    $timerDisplay.removeClass('text-primary').addClass('text-danger');
                }
            } else {
                // Cuenta hacia arriba de forma ascendente
                totalSeconds++;
            }

            $timerDisplay.text(formatTime(totalSeconds));
        }, 1000);
    });
});

$('.btnEditar').click(function() {
    $(this).siblings().prop('readonly', function(i, val) { return !val; });
    $(this).siblings().toggleClass('fw-bold');
});

$(document).ready(function() {
            
            function scrollToBottom() {
                var chatBox = $('#chat-box');
                if(chatBox.length) {
                    chatBox.scrollTop(chatBox[0].scrollHeight);
                }
            }

            scrollToBottom();

            function sendMessage() {
                var input = $('#chat-input');
                var messageText = input.val().trim();
                
                if (messageText !== "") {
                    var newMessage = $('<div><strong class="text-white">You:</strong> ' + messageText + '</div>');
                    $('#chat-box').append(newMessage);
                    input.val('');
                    scrollToBottom();
                }
            }

            $('#btn-send-chat').on('click', function(e) {
                e.preventDefault();
                sendMessage();
            });

            $('#chat-input').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            $('#btn-toggle-mic').on('click', function() {
                $(this).toggleClass('btn-disabled-status');
                $(this).find('i').toggleClass('bi-mic-fill bi-mic-mute-fill');
            });

            $('#btn-toggle-cam').on('click', function() {
                $(this).toggleClass('btn-disabled-status');
                $(this).find('i').toggleClass('bi-camera-video-fill bi-camera-video-off-fill');
            });

            $('#btn-raise-hand').on('click', function() {
                $(this).toggleClass('btn-active-hand');
            });

            $('#btn-leave').on('click', function() {
                if (confirm("Are you sure you want to leave the live class?")) {
                    alert("You have left the classroom.");
                }
            });
        });

        let localStream = null;

// 2. Función asíncrona que se comunica con el navegador
async function startWebcam() {
    try {
        localStream = await navigator.mediaDevices.getUserMedia({ 
            video: { width: { ideal: 1280 }, height: { ideal: 720 } }, 
            audio: false 
        });
        
        let videoElement = document.getElementById('local-video');
        videoElement.srcObject = localStream;
        
        // CORRECCIÓN: Forzamos al reproductor a iniciar el flujo de la cámara USB
        videoElement.play(); 
        
        $('#video-placeholder').addClass('d-none');
        $('#local-video').removeClass('d-none');
    } catch (error) {
        console.error("Error: ", error);
    }
}
// 3. Ejecuta la función inmediatamente al cargar la página
startWebcam();
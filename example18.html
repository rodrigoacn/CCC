<?php require 'menu.php'; ?>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Capturar únicamente los arreglos de texto enviados
    $textos_uno  = isset($_POST['columna_uno']) ? $_POST['columna_uno'] : [];
    $textos_dos  = isset($_POST['columna_dos']) ? $_POST['columna_dos'] : [];
    $origen      = isset($_POST['origen_pagina']) ? htmlspecialchars($_POST['origen_pagina']) : 'Desconocido';

    // 2. Validar que existan registros y no superen el límite de 5
    $total_tuplas = count($textos_uno);

    if ($total_tuplas === 0) {
        die("Error: No se seleccionó ningún registro.");
    }
    if ($total_tuplas > 5) {
        die("Error: Se ha excedido el límite máximo de 5 registros.");
    }

    echo "<h1>Procesando Datos (Sin IDs)</h1>";
    echo "<p>Página de origen: <strong>$origen</strong></p>";
    echo "<p>Total de tuplas a procesar: <strong>$total_tuplas</strong></p><hr>";

    // 3. Recorrer los textos recibidos usando su índice numérico
    foreach ($textos_uno as $indice => $texto_uno) {
        
        // Limpiar los textos por seguridad
        $texto_uno_limpio = htmlspecialchars($texto_uno, ENT_QUOTES, 'UTF-8');
        $texto_dos_limpio = isset($textos_dos[$indice]) ? htmlspecialchars($textos_dos[$indice], ENT_QUOTES, 'UTF-8') : '';

        echo "<div style='margin-bottom: 20px; padding: 10px; border: 1px solid #ccc; border-radius: 5px;'>";
        echo "<strong>Tupla #" . ($indice + 1) . "</strong><br>";
        echo "Texto Principal: " . $texto_uno_limpio . "<br>";
        
        if ($texto_dos_limpio !== "") {
            echo "Texto Secundario: " . $texto_dos_limpio . "<br>";
        } else {
            echo "Texto Secundario: <em>(No incluye segunda columna)</em><br>";
        }
        echo "</div>";

        /*
        NOTA: Si necesitas guardar esto en una base de datos sin IDs numéricos,
        puedes usar el texto limpio como referencia en tu cláusula WHERE:
        
        $sql = "UPDATE tu_tabla SET estado = 1 WHERE enunciado = :texto";
        */
    }

    echo "<br><a href='" . $origen . "' style='display:inline-block; padding:10px 15px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:5px;'>Volver a la tabla</a>";

} else {
    http_response_code(405);
    echo "Método no permitido.";
}
?>

  <div class="container-fluid px-4" style="margin-top: 4em;">
   <div class="container-fluid main-layout p-0">
        <div class="row g-0 h-100 flex-column flex-lg-row">
            
            <!-- SECCIÓN IZQUIERDA: Aula Virtual (80%) -->
            <main class="classroom-section h-100 d-flex flex-column p-3 justify-content-between" style="min-height: 0;">
                
                <!-- Video Container -->
                <div id="video-wrapper" class="video-container flex-grow-1 d-flex align-items-center justify-content-center rounded position-relative shadow border border-grey mb-3 bg-black" style="min-height: 0;">
                    
                    <!-- HTML5 Video con reproducción forzada automática -->
                    <video id="local-video" class="video-element d-none" autoplay playsinline muted></video>

                    <!-- Placeholder inicial mientras pide permisos -->
                    <div id="video-placeholder" class="text-center">
                        <i class="bi bi-camera-video display-1 text-secondary mb-3"></i>
                        <p class="text-secondary">Connecting to your hardware...</p>
                    </div>
                    
                    <!-- Miniatura de cámara secundaria (Simulación de alumno) -->
                    <div class="position-absolute bottom-0 end-0 m-3 bg-classexpress-dark rounded border border-grey p-1 d-none d-sm-block" style="width: 160px; height: 95px;">
                        <div class="w-100 h-100 bg-black d-flex align-items-center justify-content-center rounded">
                            <i class="bi bi-person-circle text-secondary fs-3"></i>
                        </div>
                    </div>
                </div>

                <!-- Controls Bar -->
                <div class="bg-classexpress-dark p-3 rounded d-flex flex-wrap gap-2 justify-content-between align-items-center border border-grey flex-shrink-0">
                    <div>
                        <h6 class="mb-0 text-truncate text-white fw-bold">Class 04: Advanced Web Development</h6>
                        <small class="text-secondary">Course: Front-End Master</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button id="btn-toggle-mic" class="btn btn-outline-secondary rounded-circle p-2 text-white border-grey" title="Mute Microphone"><i class="bi bi-mic-fill fs-5 px-1"></i></button>
                        <button id="btn-toggle-cam" class="btn btn-outline-secondary rounded-circle p-2 text-white border-grey" title="Turn Off Camera"><i class="bi bi-camera-video-fill fs-5 px-1"></i></button>
                        <button id="btn-raise-hand" class="btn btn-outline-secondary rounded-circle p-2 text-white border-grey" title="Raise Hand"><i class="bi bi-hand-index-thumb-fill fs-5 px-1"></i></button>
                        <button id="btn-leave" class="btn btn-dark border border-secondary rounded-circle p-2 text-white" title="Leave Class"><i class="bi bi-telephone-x-fill fs-5 px-1"></i></button>
                    </div>
                </div>

            </main>

            <!-- SECCIÓN DERECHA: Chat del Aula (20%) -->
            <aside class="chat-section h-100 bg-classexpress-chat border-start border-grey d-flex flex-column" style="min-height: 0;">
                
                <!-- Chat Header -->
                <div class="p-3 border-bottom border-grey text-center text-uppercase fw-bold small text-secondary tracking-wider flex-shrink-0">
                    Classroom Chat
                </div>

                <!-- Message History -->
                <div id="chat-box" class="chat-container p-3 d-flex flex-column gap-2 small flex-grow-1">
                    <div><strong>Charles_Gomez:</strong> Good afternoon, teacher! 👋</div>
                    <div><strong>Mary_Lopez:</strong> Will this class be recorded on the platform?</div>
                    <div><strong>Teacher_AI:</strong> Yes Mary, it will be available in your dashboard within 1 hour.</div>
                    <div><strong>John_Web:</strong> Is the Bootstrap code available in the repository?</div>
                    <div><strong>Moderator:</strong> Please remember to use the raise hand button to speak. 📢</div>
                </div>

                <!-- Chat Input Fields -->
                <div class="p-3 border-top border-grey bg-classexpress-dark w-100 flex-shrink-0">
                    <div class="input-group">
                        <input id="chat-input" type="text" class="form-control bg-black border-grey text-white small" placeholder="Type a question...">
                        <button id="btn-send-chat" class="btn btn-classexpress">Send</button>
                    </div>
                </div>

            </aside>

        </div>
    </div>

  </div>

  <footer class="mastfoot mt-auto">
    <div class="inner float-end">
      <p>ClassExpress done <a href="https://getbootstrap.com/">Bootstrap</a>, by <a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA">@RodrigoConejeros</a>.</p>
    </div>
  </footer>


	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script
  	src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
	<script type="text/javascript" src="./presentacion/odp_ajax.js"></script>
	<script type="text/javascript" src="./presentacion/js/scripts.js"></script>
  <script type="text/javascript" src="./script.js"></script>
</body>
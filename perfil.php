<?php require 'menu.php'; ?>
      
    <!-- ========================================== -->
  <!-- FILA 1 (Margin Top 5em) -->
  <!-- ========================================== -->
  <div class="container-fluid px-4" style="margin-top: 10em;">
    <div class="container">
<div class="row gutters">
<div class="col-xl-3 col-lg-3 col-md-12 col-sm-12 col-12">
<div class="card h-100">
	<div class="card-body">
		<div class="account-settings">
			<div class="user-profile">
				<div class="user-avatar">
					<img src="https://bootdey.com/img/Content/avatar/avatar7.png" alt="Maxwell Admin">
				</div>
				<h5 class="user-name">Yuki Hayashi</h5>
				<h6 class="user-email">yuki@Maxwell.com</h6>
			</div>
			<div class="about" style="margin-top: 0.75em;">
				<span class="badge bg-dark border border-secondary text-dorado px-2 py-0" style="font-size: 1.1rem;">
          <span style="color: #ffc107; margin-right: 4px; font-size: 1rem; line-height: 1.2;">★</span>4.2 <small>(123 reviews)</small>
        </span>
			</div>
		</div>
	</div>
</div>
</div>
<div class="col-xl-9 col-lg-9 col-md-12 col-sm-12 col-12">
<div class="card h-100">
	<div class="card-body">
		<div class="row gutters">
			<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
				<h6 class="mb-2 text-dark">Personal Details</h6>
			</div>
			<div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12">
        <div class="input-group mb-3">
          <input type="text" class="form-control inputEditar" value="Rodrigo Conejeros" readonly>
          <button class="btn btn-outline-secondary btnEditar" type="button"><i class="bi bi-pencil-square" id="iconoBoton"></i></button>
        </div>
			</div>
			<div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12">
				<div class="form-group">
          <div class="input-group mb-3">
          <input type="email" class="form-control inputEditar" value="rodrigo.conejeros@example.com" readonly>
          <button class="btn btn-outline-secondary btnEditar" type="button"><i class="bi bi-pencil-square" id="iconoBoton"></i></button>
        </div>
			</div>
      </div>
    </div>
      <div class="row gutters">
			<div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12">
				<div class="input-group mb-3">
          <input type="text" class="form-control inputEditar" value="+56935175791" readonly>
          <button class="btn btn-outline-secondary btnEditar" type="button"><i class="bi bi-pencil-square" id="iconoBoton"></i></button>
        </div>
			</div>
			<div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12">
				<div class="input-group mb-3">
          <input type="url" class="form-control inputEditar" value="https://rodrigoconejeros.example.com" readonly>
          <button class="btn btn-outline-secondary btnEditar" type="button"><i class="bi bi-pencil-square" id="iconoBoton"></i></button>
        </div>
			</div>
		</div>
		<div class="row gutters">
			<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
				<h6 class="mt-3 mb-2 text-dark">About</h6>
			</div>
			<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
        <div class="input-group mb-3">
          <textarea class="form-control inputEditar" readonly style="width: 100%; min-height: 20vh; resize: none;">
            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
          </textarea>
          <button class="btn btn-outline-secondary btnEditar position-absolute bottom-0 end-0" type="button" style="border-top-right-radius: 0;"><i class="bi bi-pencil-square" id="iconoBoton"></i></button>
        </div>
			</div>
		</div>
    <div class="row gutters">
      <div class="form-check">
        <input id="private" name="paymentMethod" type="radio" class="form-check-input" checked="" required="">
        <label class="form-check-label" for="private">Private</label>
      </div>
      <div class="form-check">
        <input id="visible" name="paymentMethod" type="radio" class="form-check-input" required="">
        <label class="form-check-label" for="visible">Visible</label>
      </div>
    </div>
		<div class="row gutters mt-5">
			<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
				<div class="text-right">
					<button type="button" id="submit" name="submit" class="btn btn-secondary">Cancel</button>
					<button type="button" id="submit" name="submit" class="btn btn-dark">Update</button>
				</div>
			</div>
		</div>
	</div>
</div>
</div>
</div>
</div>
  </div>

  <!-- Modal Eliminar Cuenta -->
  <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content bg-dark border-danger">
        <div class="modal-header border-danger">
          <h5 class="modal-title text-danger" id="deleteAccountLabel">
            <i class="bi bi-exclamation-triangle me-2"></i>Eliminar Cuenta
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger mb-3">
            <strong>⚠️ Advertencia:</strong> Esta acción es permanente. Se eliminarán todos tus datos de forma irreversible.
          </div>
          <form id="deleteAccountForm" method="POST" action="delete_account.php">
            <div class="mb-3">
              <label for="deletePassword" class="form-label text-light">Ingresa tu contraseña para confirmar:</label>
              <input type="password" class="form-control bg-secondary border-secondary text-light" id="deletePassword" name="password" placeholder="Tu contraseña" required>
            </div>
            <div class="mb-3">
              <label for="deleteConfirm" class="form-label text-light">Confirma tu contraseña:</label>
              <input type="password" class="form-control bg-secondary border-secondary text-light" id="deleteConfirm" name="confirm" placeholder="Confirma tu contraseña" required>
            </div>
          </form>
        </div>
        <div class="modal-footer border-danger">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" form="deleteAccountForm" class="btn btn-danger">Eliminar Cuenta Permanentemente</button>
        </div>
      </div>
    </div>
  </div>

  <div class="container mt-5 mb-5">
    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="card bg-dark border-danger">
          <div class="card-header bg-dark border-danger">
            <h6 class="text-danger mb-0"><i class="bi bi-exclamation-circle me-2"></i>Zona de Peligro</h6>
          </div>
          <div class="card-body">
            <p class="text-secondary small mb-3">¿Quieres eliminar tu cuenta de forma permanente? Esta acción no se puede deshacer.</p>
            <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
              <i class="bi bi-trash me-2"></i>Eliminar Cuenta
            </button>
          </div>
        </div>
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
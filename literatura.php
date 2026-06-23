<?php require 'menu.php'; ?>

  <button class="btn btn-dark position-fixed bottom-0 end-0 mb-3 me-3" type="button">Buscar Clase</button>
  <div class="container mt-10">
    <div class="jumbotron">
          <input type="text" id="input-busqueda" class="form-control bg-dark text-white border-secondary mb-4" placeholder="Search">
          <h1 class="display-3">Language an Literature</h1>
          <div class="form-check">
              <h2>Themes</h2>
              <div class="container" style="width: 100%;">
                <table class="table table-dark caption-top big-caption">
                  <caption>Evaluated Skills</caption>
                  <thead>
                    <tr>
                      <th scope="col">Choose</th>
                      <th scope="col">Theme</th>
                      <th scope="col">Description</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Locate</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Find specific, explicit information within the text.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Interpret</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Connect ideas, infer implicit information, and determine global meaning.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Evaluate</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Judge the text's content, form, argument validity, and author intent.</label></td>
                    </tr>
                  </tbody>
                </table>
                <table class="table table-dark caption-top big-caption">
                  <caption>Text Types</caption>
                  <thead>
                    <tr>
                      <th scope="col">Choose</th>
                      <th scope="col">Theme</th>
                      <th scope="col">Description</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Literary Texts</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Narrative texts like short stories, novel fragments, myths, and legends.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Non-Literary Texts</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Informative (news, reports) and argumentative (opinion columns, editorials) texts.</label></td>
                    </tr>
                  </tbody>
                </table>
                <table class="table table-dark caption-top big-caption">
                  <caption>Reading Situations</caption>
                  <thead>
                    <tr>
                      <th scope="col">Choose</th>
                      <th scope="col">Theme</th>
                      <th scope="col">Description</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Personal</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Leisure, entertainment, or individual interest texts.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Public</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Community interest, civic, or social texts.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Educational</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Academic, scientific, or textbook materials.</label></td>
                    </tr>
                  </tbody>
                </table>
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
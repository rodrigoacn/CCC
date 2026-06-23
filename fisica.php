<?php require 'menu.php'; ?>

  <button class="btn btn-dark position-fixed bottom-0 end-0 mb-3 me-3" type="button">Buscar Clase</button>
  <div class="container mt-10">
    <div class="jumbotron">
          <input type="text" id="input-busqueda" class="form-control bg-dark text-white border-secondary mb-4" placeholder="Search">
          <h1 class="display-3">Physics</h1>
          <div class="form-check">
              <h2>Themes</h2>
              <div class="container" style="width: 100%;">
                <table class="table table-dark caption-top big-caption">
                  <caption>Mechanics</caption>
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
                      <td><label class="form-check-label" for="flexCheckChecked">Kinematics</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Description of motion in a straight line, including position, displacement, velocity, acceleration, Uniform Linear Motion (MRU), and Uniformly Accelerated Linear Motion (MRUA).</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Dynamics and Forces</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Newton’s laws of motion, types of forces (weight, normal, friction, tension, and elastic force), Hooke's law, and the concept of static equilibrium.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Linear Momentum</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Concept of linear momentum (p) and impulse (I), and the principle of conservation of linear momentum in isolated systems (collisions).</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Energy and Work</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Mechanical work, kinetic energy, gravitational potential energy, elastic potential energy, the principle of conservation of mechanical energy, and mechanical power.</label></td>
                    </tr>
                  </tbody>
                </table>
                <table class="table table-dark caption-top big-caption">
                  <caption>Waves and Optics</caption>
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
                      <td><label class="form-check-label" for="flexCheckChecked">Properties of Waves</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Characteristics of a wave (amplitude, frequency, period, wavelength, and propagation speed), classification of waves (mechanical/electromagnetic, longitudinal/transverse).</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Wave Phenomena</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Reflection, refraction (including Snell's law), diffraction, interference, and absorption applied to sound and light.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Sound</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Production and propagation of sound, properties of sound (pitch, intensity, and timbre), the Doppler effect, resonance, and the anatomy and function of the human ear regarding hearing.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Light and Optics</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Electromagnetic spectrum, the nature of light, reflection and refraction in plane and curved mirrors, formation of images in converging and diverging lenses, and the anatomy and optical defects of the human eye (myopia, hyperopia).</label></td>
                    </tr>
                  </tbody>
                </table>
                <table class="table table-dark caption-top big-caption">
                  <caption>Electricity and Magnetism</caption>
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
                      <td><label class="form-check-label" for="flexCheckChecked">Electrostatics</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Electric charge, methods of charging bodies (friction, contact, and induction), and Coulomb's law regarding forces between electrical charges.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Electric Circuits</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Electric current, voltage (potential difference), resistance, Ohm’s law, electrical energy, and electrical power (Joule effect). Analysis of series, parallel, and mixed DC circuits.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Magnetism</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Magnetic poles, magnetic fields of permanent magnets, the relationship between electricity and magnetism (Oersted's discovery), and the basic principles of electromagnetic induction (Faraday's law).</label></td>
                    </tr>
                  </tbody>
                </table>
                <table class="table table-dark caption-top big-caption">
                  <caption>Earth Sciences and Universe (Macrocosmos)</caption>
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
                      <td><label class="form-check-label" for="flexCheckChecked">Earth Structure and Dynamics</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Internal layers of the Earth (crust, mantle, core), lithospheric plates, continental drift theory, plate tectonics, earthquakes (seismic waves P, S, R, L, magnitude, and intensity), and volcanism.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">The Universe and Astronomy</label></td>
                      <td><label class="form-check-label" for="flexCheckChecked">Kepler's laws of planetary motion, Newton's law of universal gravitation, the solar system, phases of the moon, eclipses, tides, the life cycle of stars, and the Big Bang theory.</label></td>
                    </tr>
                  </tbody>
                </table>
                <table class="table table-dark caption-top big-caption">
                  <caption>Advanced Classical Mechanics</caption>
                  <thead>
                    <tr>
                      <th scope="col">Choose</th>
                      <th scope="col">Theme</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Rectilinear motion (uniform and accelerated) using graphical analysis of position, velocity, and acceleration versus time.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Projectile motion (two-dimensional) and Uniform Circular Motion (angular velocity, centripetal acceleration).</label></td>
                    </tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Free-body diagrams, static/kinetic friction, tension, elastic force (Hooke's Law), and centripetal force.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Systems in static equilibrium (torque, rotational equilibrium, and center of mass).</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Kinetic energy, gravitational potential energy, and elastic potential energy.</label></td>
                    </tr>
                    </tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Conservative and Non-conservative Forces: The principle of conservation of mechanical energy and work done by friction.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Impulse and linear momentum.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Elastic and inelastic collisions in one and two dimensions (conservation of total momentum).</label></td>
                    </tr>
                  </tbody>                 
                </table>
                <table class="table table-dark caption-top big-caption">
                  <caption>Electromagnetism and Vector Fields</caption>
                  <thead>
                    <tr>
                      <th scope="col">Choose</th>
                      <th scope="col">Theme</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Electric charge, quantization, and Coulomb's Law for point charges.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Concept of the Electric Field (\(\vec{E}\)) and Electric Potential (\(V\)) for configurations of discrete charges.</label></td>
                    </tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Capacitance: Parallel plate capacitors and energy storage.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Current, voltage, resistance, and Ohm's Law.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Resistors in series and parallel, and application of Kirchhoff's Rules (junction and loop rules).</label></td>
                    </tr>
                    </tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Magnetic fields (\(\vec{B}\)) generated by moving charges and currents.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Magnetic force on moving charges (Lorentz Force) and on current-carrying wires</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Introduction to Faraday's Law of Induction and Lenz's Law (changing magnetic flux).</label></td>
                    </tr>
                  </tbody>                 
                </table>
                <table class="table table-dark caption-top big-caption">
                  <caption>Wave Mechanics, Optics, and Acoustics</caption>
                  <thead>
                    <tr>
                      <th scope="col">Choose</th>
                      <th scope="col">Theme</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Transverse and longitudinal waves, wavelength, frequency, period, and wave speed equation (\(v = \lambda \cdot f\)).</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Wave phenomena: Reflection, refraction (Snell's Law), diffraction, and interference (superposition principle).</label></td>
                    </tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Sound waves, intensity, pitch, and the Doppler Effect.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Geometric optics: Reflection in plane and spherical mirrors, refraction in thin lenses, and ray tracing.</label></td>
                    </tr>
                  </tbody>                 
                </table>
                <table class="table table-dark caption-top big-caption">
                  <caption>Thermodynamics and Fluid Mechanics</caption>
                  <thead>
                    <tr>
                      <th scope="col">Choose</th>
                      <th scope="col">Theme</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Density, pressure, and hydrostatic pressure.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Archimedes' Principle (buoyancy) and Pascal's Principle.</label></td>
                    </tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Ideal fluid dynamics: Continuity equation and Bernoulli's Equation.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Temperature scales, thermal expansion, specific heat, and latent heat (phase changes).</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Ideal Gas Law (\(PV = nRT\)) and thermodynamic processes (isobaric, isochoric, isothermal, adiabatic).</label></td>
                    </tr>
                    </tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked"></label></td>
                    </tr>
                  </tbody>                 
                </table>
                <table class="table table-dark caption-top big-caption">
                  <caption>Modern Physics and Mathematical Foundations</caption>
                  <thead>
                    <tr>
                      <th scope="col">Choose</th>
                      <th scope="col">Theme</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Wave-particle duality, Einstein’s Photoelectric Effect, and the Bohr atom model.</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Special Relativity basics: Time dilation, length contraction, and mass-energy equivalence (\(E = mc^2\)).</label></td>
                    </tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Vector algebra in Cartesian and polar coordinates (components, dot product, cross product).</label></td>
                    </tr>
                    <tr>
                      <th scope="row"><input class="form-check-input" type="checkbox" value="" id="flexCheckChecked" style="margin-left: 1.5em;"></th>
                      <td><label class="form-check-label" for="flexCheckChecked">Understanding rates of change graphically as the derivative (slope of a curve) and accumulation as the integral (area under a curve).</label></td>
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
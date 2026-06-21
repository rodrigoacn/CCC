<?php
ini_set ('error_reporting', E_ALL & ~E_NOTICE);
ini_set ('display_errors', 1);

include_once ("conexion.php");
include_once ("../../datos/odp_sql.php");

class odp_neg { 
	private $sql;
	private $ejecuta;
	private $query;
	private $id_proyecto;
	private $nombre_proyecto;
	private $max_sprint_ticket;
	private $id_ticket;
	private $ticket_dependiente;
	private $titulo_ticket;
	private $descripcion_ticket;
	private $puntos_ticket;
	private $fecha_inicio_ticket;
	private $fecha_termino_ticket;
	private $max_id_ticket;
	private $promedio_puntos;
	private $estado_ticket;
	
	public function __construct($p_usercon){ 
		$this->sql = new odp_sql();
		$this->ejecuta = $p_usercon;
	}

	public function ObtenerProyectos(){
		//Carga Vector de Parametros de entrada (OBLIGATORIO).
		//Carga variable query
		$this->sql->ObtenerProyectos();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->execute();
		$this->vector_resultado=$this->query->fetchAll();
		//return $this->ejecuta->resultado;
	}

	public function ObtenerTickets($p_id_proyecto){
		//Carga Vector de Parametros de entrada (OBLIGATORIO).
		//Carga variable query
		$this->sql->ObtenerTickets();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_proyecto", intval($p_id_proyecto));
		$this->query->execute();
		$this->vector_resultado=$this->query->fetchAll();
		//return $this->ejecuta->resultado;
	}

	public function VerSprint($p_id_proyecto){
		//Carga Vector de Parametros de entrada (OBLIGATORIO).
		//Carga variable query
		$this->sql->VerSprint();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$e_id_proyecto = intval($p_id_proyecto);
		$this->query->bindParam(":v_id_proyecto", $e_id_proyecto);
		$this->query->execute();
		$this->vector_resultado=$this->query->fetchAll();
		//return $this->ejecuta->resultado;
	}

	public function ObtenerMaxIdTicket($p_id_proyecto){
		//Carga Vector de Parametros de entrada (OBLIGATORIO).
		//Carga variable query
		$this->sql->ObtenerMaxIdTicket();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_proyecto", intval($p_id_proyecto));
		$this->query->execute();
		$this->vector_resultado=$this->query->fetchAll();
		//return $this->ejecuta->resultado;
	}

	public function EditarProyecto($p_id_proyecto, $p_nombre_proyecto){
		//Carga Vector de Parametros de entrada (OBLIGATORIO).
		//Carga variable query
		$this->sql->EditarProyecto();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_proyecto", intval($p_id_proyecto));
		$this->query->bindParam(":v_nombre_proyecto", $p_nombre_proyecto);
		$this->query->execute();
		//return $this->ejecuta->resultado;
	}

	public function EliminarTicketsDependientes($p_id_proyecto){
		//Carga Vector de Parametros de entrada (OBLIGATORIO).
		//Carga variable query
		$this->sql->EliminarTicketsDependientes();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_proyecto", intval($p_id_proyecto));
		$this->query->execute();
		//return $this->ejecuta->resultado;
	}

	public function EliminarProyecto($p_id_proyecto){
		//Carga Vector de Parametros de entrada (OBLIGATORIO).
		//Carga variable query
		$this->sql->EliminarProyecto();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_proyecto", intval($p_id_proyecto));
		$this->query->execute();
		//return $this->ejecuta->resultado;
	}

	public function EditarTicket($p_id_ticket, $p_titulo_ticket, $p_descripcion_ticket, $p_sprint_ticket, $p_estado_ticket, $p_puntos_ticket, $p_finicio_ticket, $p_ftermino_ticket){
		//Carga Vector de Parametros de entrada (OBLIGATORIO).
		//Carga variable query
		$this->sql->EditarTicket();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_ticket", intval($p_id_ticket));
		$this->query->bindParam(":v_titulo_ticket", $p_titulo_ticket);
		$this->query->bindParam(":v_descripcion_ticket", $p_descripcion_ticket);
		$this->query->bindParam(":v_sprint_ticket", intval($p_sprint_ticket));
		$this->query->bindParam(":v_estado_ticket", $p_estado_ticket);
		$this->query->bindParam(":v_puntos_ticket", intval($p_puntos_ticket));
		$this->query->bindParam(":v_finicio_ticket", $p_finicio_ticket);
		$this->query->bindParam(":v_ftermino_ticket", $p_ftermino_ticket);
		$this->query->execute();
		//return $this->ejecuta->resultado;
	}

	public function EliminarTicketsDependientes2($p_id_ticket){
		//Carga Vector de Parametros de entrada (OBLIGATORIO).
		//Carga variable query
		$this->sql->EliminarTicketsDependientes2();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_ticket", intval($p_id_ticket));
		$this->query->execute();
		//return $this->ejecuta->resultado;
	}

	public function EliminarTicket($p_id_ticket){
		//Carga Vector de Parametros de entrada (OBLIGATORIO).
		//Carga variable query
		$this->sql->EliminarTicket();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_ticket", intval($p_id_ticket));
		$this->query->execute();
		//return $this->ejecuta->resultado;
	}

	public function ObtenerPromedioDia($p_id_proyecto){
		//Carga Vector de Parametros de entrada (OBLIGATORIO).
		//Carga variable query
		$this->sql->ObtenerPromedioDia();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_proyecto", intval($p_id_proyecto));
		$this->query->execute();
		$this->vector_resultado=$this->query->fetchAll();
		//return $this->ejecuta->resultado;
	}

	public function ObtenerPromedioSemana($p_id_proyecto){
		//Carga Vector de Parametros de entrada (OBLIGATORIO).
		//Carga variable query
		$this->sql->ObtenerPromedioSemana();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_proyecto", intval($p_id_proyecto));
		$this->query->execute();
		$this->vector_resultado=$this->query->fetchAll();
		//return $this->ejecuta->resultado;
	}

	public function ObtenerPromedioMes($p_id_proyecto){
		//Carga Vector de Parametros de entrada (OBLIGATORIO).
		//Carga variable query
		$this->sql->ObtenerPromedioMes();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_proyecto", intval($p_id_proyecto));
		$this->query->execute();
		$this->vector_resultado=$this->query->fetchAll();
		//return $this->ejecuta->resultado;
	}

	public function VerificarEstadoTicket($p_id_ticket){
		$this->sql->VerificarParaleloAlumno();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_ticket", intval($p_id_ticket));
		$this->query->execute();
		$this->vector_resultado=$this->query->fetchAll();
		//return $this->ejecuta->resultado;
	}

	public function CambiarEstadoTicket($p_id_ticket, $p_estado){
		$this->sql->CambiarEstadoTicket();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_ticket", intval($p_id_ticket));
		$this->query->bindParam(":v_estado", $p_estado);
		$this->query->execute();
		//return $this->ejecuta->resultado;
	}

	public function CrearTicket($p_id_proyecto, $p_id_dependiente, $p_titulo, $p_descripcion, $p_sprint, $p_estado, $p_puntos){
		$this->sql->CrearTicket();
		$consulta=$this->sql->getQuery();
		//Envia parametros a ejecutar
		$this->query = $this->ejecuta->prepare($consulta);
		$this->query->bindParam(":v_id_proyecto", intval($p_id_proyecto));
		$this->query->bindParam(":v_id_dependiente", intval($p_id_dependiente));
		$this->query->bindParam(":v_titulo", $p_titulo);
		$this->query->bindParam(":v_descripcion", $p_descripcion);
		$this->query->bindParam(":v_sprint", intval($p_sprint));
		$this->query->bindParam(":v_estado", $p_estado);
		$this->query->bindParam(":v_puntos", intval($p_puntos));
		$this->query->execute();
		//return $this->ejecuta->resultado;
	}

	public function getSet_ODP($filas=0){
		if($this->getFilas()>0){
			$this->id_proyecto = (isset($this->vector_resultado[$filas]["id_proyecto"])) ? $this->vector_resultado[$filas]["id_proyecto"] : "";
			$this->nombre_proyecto = (isset($this->vector_resultado[$filas]["nombre_proyecto"])) ? $this->vector_resultado[$filas]["nombre_proyecto"] : "";
			$this->max_sprint_ticket = (isset($this->vector_resultado[$filas]["max_sprint_ticket"])) ? $this->vector_resultado[$filas]["max_sprint_ticket"] : "";
			$this->id_ticket = (isset($this->vector_resultado[$filas]["id_ticket"])) ? $this->vector_resultado[$filas]["id_ticket"] : "";
			$this->ticket_dependiente = (isset($this->vector_resultado[$filas]["ticket_dependiente"])) ? $this->vector_resultado[$filas]["ticket_dependiente"] : "";
			$this->titulo_ticket = (isset($this->vector_resultado[$filas]["titulo_ticket"])) ? $this->vector_resultado[$filas]["titulo_ticket"] : "";
			$this->descripcion_ticket = (isset($this->vector_resultado[$filas]["descripcion_ticket"])) ? $this->vector_resultado[$filas]["descripcion_ticket"] : "";
			$this->puntos_ticket = (isset($this->vector_resultado[$filas]["puntos_ticket"])) ? $this->vector_resultado[$filas]["puntos_ticket"] : "";
			$this->fecha_inicio_ticket = (isset($this->vector_resultado[$filas]["fecha_inicio_ticket"])) ? $this->vector_resultado[$filas]["fecha_inicio_ticket"] : "";
			$this->fecha_termino_ticket = (isset($this->vector_resultado[$filas]["fecha_termino_ticket"])) ? $this->vector_resultado[$filas]["fecha_termino_ticket"] : "";
			$this->max_id_ticket = (isset($this->vector_resultado[$filas]["max_id_ticket"])) ? $this->vector_resultado[$filas]["max_id_ticket"] : "";
			$this->promedio_puntos = (isset($this->vector_resultado[$filas]["promedio_puntos"])) ? $this->vector_resultado[$filas]["promedio_puntos"] : "";
			$this->estado_ticket = (isset($this->vector_resultado[$filas]["estado_ticket"])) ? $this->vector_resultado[$filas]["estado_ticket"] : "";
		}
	}

	public function getFilas(){
		$this->filas=count($this->vector_resultado);
		return $this->filas;
	}

	public function getIdProyecto(){
		return $this->id_proyecto;
	}

	public function getNombreProyecto(){
		return ucwords(strtolower($this->nombre_proyecto));
	}

	public function getMaxSprintTicket(){
		return $this->max_sprint_ticket;
	}

	public function getIdTicket(){
		return $this->id_ticket;
	}

	public function getTicketDependiente(){
		return $this->ticket_dependiente;
	}

	public function getTituloTicket(){
		return ucwords(strtolower($this->titulo_ticket));
	}

	public function getDescripcionTicket(){
		return ucfirst(strtolower($this->descripcion_ticket));
	}

	public function getPuntosTicket(){
		return $this->puntos_ticket;
	}

	public function getFechaInicioTicket(){
		return $this->fecha_inicio_ticket;
	}

	public function getFechaTerminoTicket(){
		return $this->fecha_termino_ticket;
	}

	public function getMaxIdTicket(){
		return $this->max_id_ticket;
	}

	public function getPromedioPuntos(){
		return $this->promedio_puntos;
	}

	public function getEstadoTicket(){
		return ucwords(strtolower($this->estado_ticket));
	}
}	
?>
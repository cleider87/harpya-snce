<?php
/*
 * SUSCERTE Copyright (C) 2014 
 * Ing. Cleider Herrera <cherrera@suscerte.gob.ve> < cleider87@gmail.com>
 *
 *  Este programa es software libre; Usted puede usarlo bajo los terminos de la
 *  licencia de software GPL version 2.0 de la Free Software Foundation.
 * 
 *  Este programa se distribuye con la esperanza de que sea util, pero SIN
 *  NINGUNA GARANTIA; tampoco las implicitas garantias de MERCANTILIDAD o
 *  ADECUACION A UN PROPOSITO PARTICULAR.
 *  Consulte la licencia GPL para mas detalles. Usted debe recibir una copia
 *  de la GPL junto con este programa; si no, escriba a la Free Software
 *  Foundation Inc. 51 Franklin Street,5 Piso, Boston, MA 02110-1301, USA.
 */
namespace sys\sensores;

use sys\Configuracion;
use sys\Conector;
use sys\modelos\Eventos;
use sys\modelos\Tipo_eventos;
use sys\Registros;
use sys\modelos\Notificaciones;
use sys\modelos\Contadores;
use DateTime;

abstract class SEN {
    /**
     * Objeto Sensor
     * @var \sys\modelos\Sensores 
     */
    protected $target;

    /**
     * Objeto Evento
     * @var \sys\modelos\Eventos; 
     */
    protected $evento;
    
    /**
     *Velocidad de descarga
     * @var Int 
     */
    protected static $velocidad=0;
    
    /**
     * Inicio de tareas del sensor
     * @param \sys\modelos\Sensores $sensor
     */
    public function iniciar($sensor){
        
    }
    
    /**
     * Función que crea y actualiza eventos generados
     * @param \sys\modelos\Sensores $sensor
     */
    public function gestionarEvento($id_evento,$sensor,$descripcion,$log){
        //Instanciar tipo de evento a registrar
        $tipo_evento_nuevo=new Tipo_eventos();
        $tipo_evento_nuevo->setCampo('id',$id_evento);
        $tipo_evento_nuevo->cargarData();
        Registros::principal("EVENTO",'Control de eventos');
	$busqueda=Conector::ejectSQL("SELECT * FROM t_eventos WHERE status=1 AND id_sensor=".$sensor->verCampo('id'));
        $id=null;
        foreach ($busqueda as $event) {
            if(!empty($event['id'])){
                $id=$event['id'];
            }  
        }
        if(!empty($id)){
            //Obteniendo datos del evento anterior
            $evento_anterior=new Eventos();
            $evento_anterior->setCampo('id', $id);
            $evento_anterior->cargarData();
            
            //Instanciar tipo de evento anterior
            $tipo_evento_anterior=new Tipo_eventos();
            $tipo_evento_anterior->setCampo('id',$evento_anterior->getCampo('id_tipo_evento'));
            $tipo_evento_anterior->cargarData();
            
            $prioridad_1=$tipo_evento_anterior->getCampo('id_prioridad');
            $prioridad_2=$tipo_evento_nuevo->getCampo('id_prioridad');
            // Finalizar evento
            if($id_evento=='1'){
                // Entrando en modo normal
                if($tipo_evento_anterior->getCampo('id')!=='1'){
                    // Aca se debe evaluar si se notifica o no
                    $this->finalizarEvento();
                    $this->crearNotificacion($evento_anterior,$tipo_evento_anterior);
                    $this->gestionarEvento($id_evento,$sensor,$descripcion,$log);
                }else{
                    //Actualizando estadistica de velocidad
                    $evento_anterior=$this->calculoVelocidad($evento_anterior);
                    $evento_anterior->actualizarData();
                    //Registro de Log Individual
                    Registros::regEvento($id, $log);
                }
            }else{
                // Entrando en modo normal
                if($tipo_evento_anterior->getCampo('id')=='1'){
                    echo "Cerrando Evento \n";
                    $this->finalizarEvento();
                    $this->gestionarEvento($id_evento,$sensor,$descripcion,$log);
                }else{
                    //Actualizando estadistica de velocidad
                    $evento_anterior=$this->calculoVelocidad($evento_anterior);
                    $evento_anterior->actualizarData();
                    //Registro de Log Individual
                    Registros::regEvento($id, $log);
                }
            }
            
        }else{
            // Creando nuevo evento
            $evento_nuevo=new Eventos();           
            $evento_nuevo->setCampo('id_sensor',$sensor->verCampo('id'));
            $evento_nuevo->setCampo('id_tipo_evento',$id_evento);
            $evento_nuevo->setCampo('id_monitor',  Configuracion::getID());
            $evento_nuevo->setCampo('d_inicio',date(DATE_ATOM));
            $evento_nuevo->setCampo('id_ac',  $this->target->getCampo('id_ac'));
            $evento_nuevo->setCampo('d_kbps_max',self::$velocidad);
            $evento_nuevo->setCampo('d_kbps_min',self::$velocidad);
            $evento_nuevo=$this->calculoVelocidad($evento_nuevo);
            $evento_nuevo->setCampo('d_descriptor',$descripcion);
            $evento_nuevo->setCampo('status',1);
            $evento_nuevo->agregarData();
            $busqueda_nuevo=Conector::ejectSQL("SELECT * FROM t_eventos WHERE status=1 AND id_sensor=".$sensor->verCampo('id'));
            $id_nuevo=null;
            foreach ($busqueda_nuevo as $valor) {
                if(!empty($valor['id'])){
                    $id_nuevo=$valor['id'];
                    $evento_nuevo->setCampo('id', $id_nuevo);
                }
            }
            
            if ($id_evento=='4'){
                $tipo_evento_nuevo= new Tipo_eventos();
                $tipo_evento_nuevo->setCampo('id', $id_evento);
                $tipo_evento_nuevo->cargarData();
                $this->crearNotificacion($evento_nuevo,$tipo_evento_nuevo);
            }
            //Registro de Log Individual
            Registros::regEvento($id_nuevo, $log);
        }
    }

    /**
     * Función que finaliza eventos
     * 
     */
    public function finalizarEvento(){
        $busqueda=Conector::ejectSQL("SELECT * FROM t_eventos WHERE status=1 AND id_sensor=".  $this->target->getCampo('id'));
        foreach ($busqueda as $event) {
            if(!empty($event['id'])){
                //Obteniendo datos del evento anterior
                $evento_anterior=new Eventos();
                $evento_anterior->setCampo('id', $event['id']);
                $evento_anterior->cargarData();
                $evento_anterior=$this->calculoVelocidad($evento_anterior);
                $evento_anterior->setCampo('d_fin', date(DATE_ATOM));                    
                $evento_anterior->setCampo('status',0);
                $evento_anterior->actualizarData();
                Registros::regEvento($event['id'],'Finalizando Evento');
                // Obtener datos de tipo de evento
                $tipo_evento_anterior=new Tipo_eventos();
                $tipo_evento_anterior->setCampo('id',$evento_anterior->getCampo('id_tipo_evento'));
                $tipo_evento_anterior->cargarData();
                
                // Contabilizar tiempo de falla
                if($tipo_evento_anterior->getCampo('d_suma')){
                    // Cálculo del tiempo de la falla
                    $inicio=new DateTime(date('Y-m-d H:i:s',strtotime("+0 minutes",strtotime($evento_anterior->verCampo('d_inicio')))));
                    $fin=new DateTime(date('Y-m-d H:i:s',strtotime("+0 minutes",strtotime($evento_anterior->verCampo('d_fin')))));
                    $interval = $fin->diff($inicio);
                    $hs=(int)$interval->format('%h');
                    $ms=(int)$interval->format('%i');
                    // Minutos de duración de la falla
                    $tiempo_min=$hs*60+$ms;
                    // Busqueda de contador para la ac del mes
                    $busqueda_cont=  Conector::ejectSQL("SELECT * FROM t_contadores WHERE d_mes=".date('m')
                            ." AND d_anio=".date('Y')." AND id_sensor=".$evento_anterior->getCampo('id_sensor'));
                    $id_nuevo=null;
                    foreach ($busqueda_cont as $cont) {
                        if(!empty($cont['id'])){
                            $id_nuevo=$cont['id'];
                        }
                    }
                    // Instancia de contador
                    $contador=new Contadores();
                    if($id_nuevo!==null){
                        // Existe, se actualizará
                        $contador->setCampo('id', $id_nuevo);
                        $contador->cargarData();
                        
                        $min_ant=(int)$contador->getCampo('d_incumple_min');
                        $revisiones=(int)$contador->getCampo('d_revisiones');
                        
                        $contador->setCampo('d_incumple_min', $min_ant+$tiempo_min);
                        $contador->setCampo('d_revisiones', $revisiones+1);
                        $contador->actualizarData();
                    }else {
                        // No existe, se crea uno nuevo
                        $contador->setCampo('id_ac', $evento_anterior->getCampo('id_ac'));
                        $contador->setCampo('id_sensor', $evento_anterior->getCampo('id_sensor'));
                        $contador->setCampo('d_mes',date('m'));
                        $contador->setCampo('d_anio',date('Y'));
                        $contador->setCampo('d_incumple_min', $tiempo_min);
                        $contador->setCampo('d_revisiones', 1);
                        $contador->agregarData();
                    }
                }
                break;
            }
        }
        
    }
    
    /**
     * Actualiza el evento
     * 
     * @param Eventos $evento
     * @return Eventos
     */
    public function calculoVelocidad($evento){
        $nueva_velocidad=self::$velocidad;
        $v_max= $evento->getCampo('d_kbps_max');
        $v_min= $evento->getCampo('d_kbps_min');
        if($v_max<=$nueva_velocidad){
            $evento->setCampo('d_kbps_max', $nueva_velocidad);
        }  else {
            if($v_min>=$nueva_velocidad){
                $evento->setCampo('d_kbps_min', $nueva_velocidad);
            }
        }
        return $evento;
    }
    
    /**
     * Función para descargar archivos de internet a traves de Curl
     * 
     * @param String $url
     * @param String $nombre
     * @return String
     */
    public static function descargaCurl($url,$nombre,$otro_dir=null){
        $ch = curl_init();
        echo "Ejecutando ".$url."\n";
        Registros::principal("SENSOR",'Obteniendo datos de '.$url);
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,TRUE);
        $protocolo=split("://",$url);
        if($protocolo[0]=="https"){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        }
        $salida=curl_exec($ch);
        $ultimaUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $info = curl_getinfo($ch);
        if($ultimaUrl===$url){
            echo "Archivo encontrado\n";
            Registros::principal("SENSOR",'Descargando '.$nombre);
            curl_close($ch);
        }else{
            curl_close($ch);
            $ch = curl_init();
            echo "Redireccionando\n";
            Registros::principal("SENSOR",'Redireccionando '.$ultimaUrl);
            curl_setopt($ch,CURLOPT_URL,$ultimaUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $protocolo=split("://",$ultimaUrl);
            if($protocolo[0]=="https"){
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
            }
            echo "Descargando ".$ultimaUrl."...\n";
            Registros::principal("SENSOR",'Obteniendo información de '.$ultimaUrl);
            $salida = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
        }
        echo $nombre." descargó a ".$info['speed_download']." Bytes/s \n";
        //Obtiene la velocidad de descarga en bytes por segundo y la convierte en kilobytes por segundo
        self::$velocidad=($info['speed_download']*8/1024);
        if($otro_dir==null){
            $fm=fopen(Configuracion::getLcr()."temp/".$nombre,'w');
        }  else {
            $fm=fopen($otro_dir.$nombre,'w');
        }
        fwrite($fm,$salida);
        fclose($fm);
        return $salida;
    }
    
    /**
     * Función para ejecutar pruebas de conexión con urls distintas al 
     * 
     * @param type $url
     * @return int
     */
    public function evaluarURL($url){
        // Inicializando contadores
        $conexiones=0;
        $conectadas=0;
        //Intentando conectar con el PSC
        $fp=@fopen($url,'r');
        // Obteniendo Objetivos de evaluación
        $urls=Conector::ejectSQL('SELECT * FROM t_pruebas WHERE status=1');
        // Evaluando Conexión
        foreach ($urls as $key => $test) {            
            $conexiones++;
            Registros::principal("SENSOR",'Sensor ID '.$this->target->verCampo('id').' Evaluando conexión con '.$test['d_url']);
            $fx=@fopen($test['d_url'],'r');
            if($fx!=true){
               //Conexiones prueba Falla
               Registros::principal("SENSOR",'Sensor ID '.$this->target->verCampo('id').' Host inalcanzable '.$test['d_url']);
            }else{
               Registros::principal("SENSOR",'Sensor ID '.$this->target->verCampo('id').' Conexión exitosa con '.$test['d_url']);
               $conectadas++;
            }
        }
        $retorno=1;
        //Conexión PSC Falla
        if($fp!=true){
            if($conectadas<$conexiones){
                // Ninguna dirección de prueba conectó
                if($conectadas==0){
                    // Monitor sin conexión
                    $retorno=2;
                    $this->target->setCampo('d_minutos',5);
                    // Manejando Evento
                    $this->gestionarEvento(2,$this->target,'Monitor sin conexión',
                            'Se analizaron otras URLs y no se puede establecer conexión');
                }  else {
                    if($conectadas==$conexiones){
                        // Conexiones de prueba exitosas todas
                        if($conexiones>0){
                            $retorno=3;
                            $this->target->setCampo('d_minutos',1);
                            // Manejando Evento
                            $this->gestionarEvento(3,$this->target,'Servidor del PSC sin conexión',
                                    'Se analizaron otras URLs con 100% disponibilidad');
                        }
                    }else{
                        // Monitor con conexión
                        $this->target->setCampo('d_minutos',1);
                        $retorno=3;
                        // Manejando Evento
                        $this->gestionarEvento(3,$this->target,'Servidor del PSC sin conexión',
                                'Se analizaron otras URLs y se obtuvo conexión en '.$conectadas." de ".$conexiones
                                .' Sitios Web');
                    }
                }
            }
        //Conexión PSC Exitosa    
        }else{
            // Conexión con PSC funciona
            $this->target->setCampo('d_minutos',5);
            if($conectadas<$conexiones){
                // Conexión limitada o intermitente
                $retorno=4;
                // Manejando Evento
                $this->gestionarEvento(4,$this->target,'El recurso no está disponible',
                        'Se logró establecer conexión con '.$url.' y '.$conectadas.'/'.$conexiones.
                        ' URLs de prueba, pero no con el recurso. Conexión intermitente o limitada');
            }
            //
            if($conectadas==$conexiones){
                // Recurso no localizado
                $retorno=4;
                // Manejando Evento
                $this->gestionarEvento(4,$this->target,'El recurso no está disponible',
                        'Se logró establecer conexión con '.$url.' y '.$conectadas.'/'.$conexiones.
                        ' URLs de prueba, pero no con el recurso.');
            }
            $this->target->setCampo('d_minutos',5);
        }
        return $retorno;
    }
    
    /**
     *  Crea y lista los usuarios a notificar por un evento específico
     * 
     * @param Eventos $evento
     * @param Tipo_eventos $tipo_evento
     */
    public function crearNotificacion($evento,$tipo_evento){
        // Tomando instancias
        $sensor=$this->target;
        // Generando cuerpo de la notificación
        $cuerpo="<body>"
                    . "<h6>".$sensor->verCampo('d_nombre')."</h6><p>"
                    .'Inicio: '.$evento->verCampo('d_inicio').'<br>'
                    .'Fin: '.  str_ireplace('T', ' ', date(DATE_ATOM)).'<br>'
                    .'</p>'
                . "</body>";
        // Listando usuarios a notificar
        $sql="SELECT * FROM t_usuarios WHERE d_notificaciones like '%".$sensor->getCampo('id_tipo').";%'";
        $usuarios_consulta=Conector::ejectSQL($sql);
        foreach ($usuarios_consulta as $user) {
            // Creando Notificación
            $notificacion=new Notificaciones();
            $notificacion->setCampo('id_usuario', $user['id']);
            $notificacion->setCampo('id_sensor', $sensor->getCampo('id'));
            $notificacion->setCampo('id_evento', $evento->getCampo('id'));
            $notificacion->setCampo('id_tipo_evento', $tipo_evento->getCampo('id'));
            $notificacion->setCampo('d_titulo',$tipo_evento->verCampo('d_nombre'));
            $notificacion->setCampo('d_momento',date(DATE_ATOM));
            $notificacion->setCampo('d_contenido',$cuerpo);
            $notificacion->setCampo('d_visto',0);
            $notificacion->setCampo('d_enviado',0);
            $notificacion->agregarData();
        }
    }

//
}
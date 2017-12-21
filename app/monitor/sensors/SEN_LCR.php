<?php
/*
 * SUSCERTE Copyright (C) 2014 
 * Ing. Cleider Herrera < cleider87@gmail.com>
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
use sys\sensores\SEN;
use sys\Registros;
use sys\Util;
use DateTime;
use sys\modelos\Lcr;
use sys\Conector;
/**
 * Description of SEN_LCR
 *
 * @author Ing. Cleider Herrera < cleider87@gmail.com>
 */
class SEN_LCR extends SEN {
   
    /**
     * 
     * @param \sys\modelos\Sensores $sensor
     */
    public function iniciar($sensor) {
        $this->target=$sensor;
        $this->evaluar();
        $this->target->actualizarData();
        
    }
    
    public function getPlazo() {
        return '+24 hours';
    }
    
    /**
     * Función que hace pruebas al sensor
     */
    public function evaluar() {
        Registros::principal("SENSOR","Iniciando verificación de LCR");
        static::monitorear();
    }
    
    /**
     * Función que descarga y evalua la url
     * 
     * @return int
     */
    public function monitorear(){
        //Descargando LCR
        $lista=$this->bajarLCRconCurl();
        if($lista!==null){
            $lista_por_lineas=explode("\n",$lista);
            echo $serial= Util::valorCampoInterno($lista_por_lineas, 'X509v3 CRL Number');
            echo "\n";
            if($serial=='-'){
                $serial=date('Y-m-d');
            }
            echo "Ultima actualización: ";
            echo $ultima=Util::valorCampo($lista_por_lineas,'Last Update');
            echo "\nSiguiente Actualización: ";
            echo $siguiente=Util::valorCampo($lista_por_lineas,'Next Update');
            echo "\n";
            /**
             * Arreglo con minutos y tipo de falla
             */
            $codigo=static::compararFechas($ultima, $siguiente,$this->getPlazo());
            //Almacenar en base de datos la lista
            $consulta_lcr=Conector::ejectSQL("SELECT * FROM t_lcr WHERE d_fecha='".
                    date('Y-m-d').'\' AND id_ac='.$this->target->getCampo('id_ac')
                    ." AND d_serial='".$serial.'\'');
            $existe=FALSE;
            foreach ($consulta_lcr as $valor) {
                if(!empty($valor['id'])){
                    $existe=TRUE;
                } 
            }
            if(!$existe&&$lista!==''){
                $lcr=new Lcr();
                $lcr->setCampo('id_ac', $this->target->getCampo('id_ac'));
                $lcr->setCampo('d_fecha', date('Y-m-d'));
                $lcr->setCampo('d_serial',$serial);
                $lcr->setCampo('d_last_update',$ultima);
                $lcr->setCampo('d_next_update',$siguiente);
                $lcr->setCampo('d_contenido',$lista);
                $lcr->agregarData();
            }
        }  else {
            return null;
        }
        return $codigo;
    }
    
    /**
     * Función que evalua la fecha de de ultima y siguiente actualización de la 
     * LCR
     * 
     * @param String $ult
     * @param String $sig
     * @param String $intervalo
     */
    
    public function compararFechas($ult,$sig,$intervalo){
        //Valores necesarios
        $psc=$this->target->verCampo('d_nombre');
        //Inicializando retorno de funcion
        $tiempo=0;
	$uAct=strtotime($intervalo, strtotime($ult));
	$nAct=strtotime("+0 minutes",strtotime($sig));
        // Creando objetos DateTime para diferenciar fechas
	$fecha1 = new DateTime(date('Y-m-d H:i:s',$uAct));
	$fecha2 = new DateTime(date('Y-m-d H:i:s',time()));
        // Cálculo de intervalo transcurrido
	$interval = $fecha1->diff($fecha2);
	// Determinando la fecha mayor
	if($fecha1<=$fecha2){
            // Registra actividad
            Registros::principal("SENSOR",'La LCR está desactualizada');
            $hs=(int)$interval->format('%h');
            $ms=(int)$interval->format('%i');
            $tiempo=$hs*60+$ms;
            // Fijando siguiente revisión
            $this->target->setCampo('d_minutos',1);
            $log_5='Se detectaron '.$tiempo.' minutos desactualizados';
            Registros::principal("SENSOR",$log_5);
            // Manejando Evento
            $this->gestionarEvento(5,$this->target,'LCR Caducada',$log_5);
	}else{
            $log_1='PSC '.$psc.' ha actualizado correctamente su LCR';
            Registros::principal("SENSOR",$interval->format('Faltan %a día(s), %h hora(s), %i minuto(s) y %s segundo(s) para la seguiente actualización'));
            Registros::principal("SENSOR",$log_1);
            $hs=(int)$interval->format('%h');
            $ms=(int)$interval->format('%i');
            $this->target->setCampo('d_minutos',30);
            // Manejando Evento
            $this->gestionarEvento(1,$this->target,'LCR Correcta',$log_1);
            // Cálculo de intervalo transcurrido
            $fecha3 = new DateTime(date('Y-m-d H:i:s',$uAct));
            $fecha4 = new DateTime(date('Y-m-d H:i:s',$nAct));
            $interval2 = $fecha4->diff($fecha3);
            // Determinando la fecha mayor
            $da=(int)$interval2->format('%a');
            $ha=(int)$interval2->format('%h');
            $ma=(int)$interval2->format('%i');
            $suma=$da*24*60+$ha*60+$ma;
            if($suma!=0){
                // Registra actividad
                $log_6='PSC '.$psc.' tiene error en la fecha de se siguiente actualización de la LCR ';
                Registros::principal("SENSOR",$log_6);
                // Manejando Evento
                $this->gestionarEvento(6,$this->target,'Error Siguiente Fecha Actualización',$log_6);
            }else{
                // Registra actividad
                $log_1='Siguiente fecha de actualización correcta';
                Registros::principal("SENSOR",$log_1);
                // Manejando Evento
                $this->gestionarEvento(1,$this->target,'Siguiente fecha de actualización correcta',$log_1);
                //$this->finalizarEvento();
            }
        }
        return $tiempo;
    }    
 
 public function bajarLCRconCurl(){
        $url=$this->target->verCampo('d_url');
        $contenido_lista="";
        $DIR=Configuracion::getLcr();
        Registros::principal("SENSOR",'Iniciando descarga LCR');
        //Construye el nombre del archivo a descargar
        $nombreArcLCR=substr(strrchr($url,"/"),1);
        //Construye el url principal
 	$urlRaiz=explode("/", $url);
        $urlRaiz2=$urlRaiz[0]."//".$urlRaiz[2];
        //Intenta abrir la conexión
        $fp=@fopen($url,'r');
        if($fp!=true){
            // No conecta    
            Registros::principal("SENSOR",'No se pudo conectar con '.$url);
            $fp=@fopen($url,'r');
            if ($fp!=true){
                $code=$this->evaluarURL($urlRaiz2);
                $this->target->setCampo('d_minutos',2);
                //Implementar codigo de error
            }
            return null;
        }
        if($fp!=false){
            try {
            // Registro de caso
                //Conexión establecida
                Registros::principal("SENSOR",'Conexión establecida con '.$url);
                // Desconecta el URL
                fclose($fp);
                //Descarga el CRL en, lo almacena y registra esa actividad
                Registros::principal("SENSOR",'Almacenando datos');
                static::descargaCurl($url,$nombreArcLCR)."\n";
                $contenido_lista= Util::openLCR_temporal($nombreArcLCR, $this->target->verCampo('id_ac'));
                //creando nombre
                $nombre="AC_". $this->target->getCampo('id_ac')."_".Util::valorCampoInterno(explode("\n", $contenido_lista),"X509v3 CRL Number");
                //Registrando
                Registros::principal("SENSOR",'Respaldando LCR en formato .crl');
                $data_crl=file_get_contents($DIR."temp/".$nombreArcLCR);
                $f=file_exists(Configuracion::getLcr_crl().$nombre.".crl");
                if ($f==true){
                    Registros::principal("SENSOR","Archivo ".$nombre.".crl ya existe.");
                }else{
                    Registros::principal("SENSOR","Archivo ".$nombre.".crl guardado.");
                    $fm=fopen(Configuracion::getLcr_crl().$nombre.".crl",'w');
                    fwrite($fm,$data_crl);
                    fclose($fm);
                }
                Util::terminal("rm ".$DIR."/temp/".$nombreArcLCR);
            } catch (Exception $exc) {
                echo $exc->getTraceAsString();
                $log_4='Servidor desconectó esta terminal automáticamente';
                //Servidor desconectó esta terminal automáticamente
                Registros::principal("SENSOR",$log_4);
                // Manejando Evento
                $this->gestionarEvento(4,$this->target,'Descarga de archivo abortada',$log_4); 
            }
        }
        return $contenido_lista;
    }
}



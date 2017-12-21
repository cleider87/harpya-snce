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
use sys\sensores\SEN;
use sys\Registros;
use sys\Configuracion;
use sys\Util;

/**
 * Description of OCSP
 *
 * @author Ing. Cleider Herrera < cleider87@gmail.com>
 */
class SEN_OCSP  extends SEN{
    
    public function iniciar($sensor) {
        $this->target=$sensor;
        $this->evaluarOCSP();
        $this->target->actualizarData();
        
    }
    private function evaluarOCSP(){
        $id_sensor=  $this->target->getCampo('id');
	// Registrando en LOG y en Informe
        Registros::principal("SENSOR ".$id_sensor, "Iniciando Verificación de OCSP");
        $ruta=Configuracion::getAc();
        $ruta_temporal=  Configuracion::getTemp();
        $id_ac=$this->target->getCampo('id_ac');
	// Creando comando OpenSSL a ejecutar
	// Este comando realiza consulta de certificado con el archivo PEM del mismo a traves de OCSP	
	$comand="openssl ocsp -issuer ".$ruta."CA_".$id_ac.".pem -CAfile ".
        $ruta."cadena-confianza.pem -url ".$this->target->verCampo('d_url')." -cert ".
        $ruta.$id_ac.".pem -no_nonce -text -out ".$ruta_temporal.$id_ac.".ocsp";
	Registros::principal("SENSOR ".$id_sensor,"Esperando respuesta de Servicio OCSP");
	Util::terminal($comand);
        $this->descargaCurl($this->target->verCampo('d_url'),$id_ac.".prueba");
        $contenido=file_get_contents($ruta_temporal.$id_ac.".ocsp");
        Util::terminal("rm ".$ruta_temporal.$id_ac.".ocsp");
        echo "\n";
	Registros::principal("SENSOR ".$id_sensor,"Respuesta Obtenida");
	// Realizando operaciones con el response
	Registros::principal("SENSOR ".$id_sensor,"Evaluando Respuesta");
	echo $resp=Util::valorCampo(explode("\n",$contenido),"OCSP Response Status");
        echo "\n";
        // Determinar si hubo response
        $corta=  explode(" ", $resp);
        switch ($corta[0]) {
            case 'successful':
                $log="OCSP Funciona Adecuadamente";
                Registros::principal("SENSOR ".$id_sensor,$log);
                $this->target->setCampo('d_minutos',15);
                $this->gestionarEvento(1,$this->target,'OCSP Funciona adecuadamente',$log." ".$resp);
                break;
            case 'malformedRequest':
                $log="Respuesta OCSP dañada";
                Registros::principal("SENSOR ".$id_sensor,$log);
                $this->target->setCampo('d_minutos',15);
                $this->gestionarEvento(6,$this->target,'OCSP no construyó la respuesta correctamente ',$log." ".$resp);
                break;
            case 'internalError':
                $log="Respuesta OCSP no válida";
                Registros::principal("SENSOR ".$id_sensor,$log);
                $this->target->setCampo('d_minutos',5);
                $this->gestionarEvento(6,$this->target,'OCSP no construyó la respuesta correctamente ',$log." ".$resp);
                break;
            case 'tryLater':
                $log="Respuesta OCSP no disponible";
                Registros::principal("SENSOR ".$id_sensor,$log);
                $this->target->setCampo('d_minutos',10);
                $this->gestionarEvento(8,$this->target,'OCSP no tiene respuesta disponible, intente luego ',$log." ".$resp);
                break;
            case 'sigRequired':
                $log="Respuesta OCSP sin firma";
                Registros::principal("SENSOR ".$id_sensor,$log);
                $this->target->setCampo('d_minutos',10);
                $this->gestionarEvento(8,$this->target,'OCSP no puede responder ',$log." ".$resp);
                break;
            case 'unauthorized':
                $log="Respuesta OCSP sin autorización para responder";
                Registros::principal("SENSOR ".$id_sensor,$log);
                $this->target->setCampo('d_minutos',10);
                $this->gestionarEvento(8,$this->target,'OCSP no puede responder ',$log." ".$resp);
                break;
            case '-':
                $log="Servicio OCSP fuera de línea";
                Registros::principal("SENSOR ".$id_sensor,$log);
                $this->target->setCampo('d_minutos',1);
                $this->evaluarURL($this->target->verCampo('d_url'));
                $this->gestionarEvento(4,$this->target,'OCSP fuera de servicio',$log." ".$resp);
                break;
            default:
                break;
        }
    }
    
    
}

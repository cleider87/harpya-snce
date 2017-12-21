<?php

/*
 * Cleider Herrera Copyright (C) 2015 
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
 * Description of SEN_DPC
 *
 * @author Ing. Cleider Herrera < cleider87@gmail.com>
 */
class SEN_DPC extends SEN{
    /**
     * 
     * @param \sys\modelos\Sensores $sensor
     */
    public function iniciar($sensor) {
        $this->target=$sensor;
        $this->evaluar();
        $this->target->actualizarData();
        
    }
    
    public function evaluar(){
        $url=$this->target->verCampo('d_url');
        $contenido_archivo="";
        Registros::principal("SENSOR",'Iniciando descarga de DPC');
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
                $this->target->setCampo('d_minutos',5);
                //Implementar codigo de error
            }
            return null;
        }
        if($fp!=false){
            try {
                //Conexión establecida
                Registros::principal("SENSOR",'Conexión establecida con '.$url);
                // Desconecta el URL
                fclose($fp);
                //Descarga archivo
                Registros::principal("SENSOR",'Almacenando datos del Archivo');
                $contenido_archivo=  $this->descargaDocumento($url,$nombreArcLCR,Configuracion::getDpc())."\n";
                $hash_descarga=md5($contenido_archivo);
                if($this->target->verCampo('d_adicional')==''){
                    $this->target->setCampo('d_adicional',$hash_descarga);
                }else{
                    if($hash_descarga===$this->target->verCampo('d_adicional')){
                        $log="El resumen hash(md5) coincide satisfactoriamente";
                        $this->gestionarEvento(1,$this->target,'El resumen hash coincide',$log." ".$hash_descarga);
                        // Fijando siguientee ejecución en un día
                        $this->target->setCampo('d_minutos',60*24);
                        
                    }else{
                       $log="El resumen hash(md5) no coincide con lo registrado en la base de datos";
                        $this->gestionarEvento(9,$this->target,'El resumen hash no coincide',$log." ".$hash_descarga);
                        // Fijando siguientee ejecución en 1 hora
                        $this->target->setCampo('d_minutos',60);
                    }
                }
                Util::terminal("rm ".Configuracion::getDpc().$this->target->verCampo('id_ac')."-".$nombreArcLCR);
            } catch (Exception $exc) {
                echo $exc->getTraceAsString();
                $log_4='Servidor desconectó esta terminal automáticamente';
                //Servidor desconectó esta terminal automáticamente
                Registros::principal("SENSOR",$log_4);
                // Manejando Evento
                $this->gestionarEvento(4,$this->target,'Descarga de archivo abortada',$log_4);
                // Fijando siguientee ejecución en 1 hora
                $this->target->setCampo('d_minutos',60);
            }
        }
    }
    
    
     /**
     * Función para descargar archivos de internet a traves de Curl
     * 
     * @param String $url
     * @param String $nombre
     * @return String
     */
    public function descargaDocumento($url,$nombre,$otro_dir=null){
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
            $fm=fopen(Configuracion::getLcr()."temp/".$this->target->verCampo('id_ac')."-".$nombre,'w');
        }  else {
            $fm=fopen($otro_dir.$this->target->verCampo('id_ac')."-".$nombre,'w');
        }
        fwrite($fm,$salida);
        fclose($fm);
        return $salida;
    }
}

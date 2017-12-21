<?php

namespace sys\modelos;
use sys\Modelo;
use sys\Conector;
/**
 * Description of Usuarios
 *
 * @author Ing. Cleider Herrera < cleider87@gmail.com>
 */
class Usuarios extends Modelo{
  protected $id;
  protected $id_rol; 
  protected $d_nombre;
  protected $d_usuario;
  protected $d_clave;
  protected $d_fallas;
  protected $d_ultima_conexion;
  protected $d_correo;
  protected $d_notificaciones;
  protected $status;
  
  public function login($usuario,$clave){
        try {
            if (session_status()==PHP_SESSION_NONE){
                session_start();
            }
        } catch (Exception $ex) {

        }
        $login=FALSE;
        $result=Conector::ejectSQL("SELECT id FROM t_usuarios WHERE status=1 AND d_usuario='".$usuario."'");
        $id=NULL;
        foreach ($result as $valor) {
           $id=$valor['id'];
        }
        if($id!==NULL){
            $this->setCampo('id',$id);
            $this->cargarData();
            $this->setCampo('d_ultima_conexion',date(DATE_W3C));
            /////////////////////////////////////////////////////////////
            // Código temporal, reiniciar clave
            //$this->setCampo('d_clave',  $this->generarClave($clave));
            //$this->actualizarData();
            /////////////////////////////////////////////////////////////
            $i=intval($this->getCampo('d_fallas'));
            if(strcmp($this->generarClave($clave),str_replace("'","",$this->getCampo('d_clave')))==0){
                $login=TRUE;
                $this->setCampo('d_fallas',0);
            }  else {
                $_SESSION['mensaje']="Intento fallido ".($i+1);
                if($i==2){
                    $this->setCampo('d_fallas',0);
                    $this->setCampo('status',0);
                    $_SESSION['mensaje']="Usuario bloqueado por intentos fallidos";
                }else{
                    $this->setCampo('d_fallas',$i+1);
                }
            }
            $this->actualizarData();
        }else{
            $_SESSION['mensaje']="Usuario y/o Clave Incorrecta.";
        }
        return $login;
   }
   
   public function generarClave($clave){
        return hash('sha512',$clave."".$_SERVER['sec']);
   }
   
   
   
   
}

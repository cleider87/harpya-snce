<?php

namespace sys\modelos;
use sys\Modelo;
/**
 * Description of Sensores
 *
 * @author Ing. Cleider Herrera < cleider87@gmail.com>
 */
class Sensores extends Modelo{
    protected $id;
    protected $id_ac;
    protected $id_tipo;
    protected $d_nombre;
    protected $d_url_opcional;
    protected $d_url;
    protected $d_minutos;
    protected $d_ult_ejecucion;
    protected $d_adicional;
    protected $status;	
}

<?php

namespace sys\modelos;
use sys\Modelo;
/**
 * Description of Eventos
 *
 * @author Ing. Cleider Herrera < cleider87@gmail.com>
 */
class Eventos extends Modelo{
    protected $id;    
    protected $id_tipo_evento;
    protected $id_monitor;
    protected $id_ac;
    protected $id_sensor;
    protected $d_inicio;
    protected $d_fin;
    protected $d_kbps_max;
    protected $d_kbps_min;
    protected $d_descriptor;
    protected $status;
}

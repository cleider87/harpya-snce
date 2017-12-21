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
use sys\sensores\SEN_LCR;

/**
 * Description of SEN_LCR_root
 *
 * @author Ing. Cleider Herrera < cleider87@gmail.com>
 */
class SEN_LCR_RAIZ extends SEN_LCR{
    
    public function getPlazo() {
        return '+180 days';
    }
}

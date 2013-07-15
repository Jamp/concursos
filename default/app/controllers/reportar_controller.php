<?php

class ReportarController extends AppController {
    public $meses = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
    private $sabados = 6;
    private $segundos_dias = 86400;

    public function index($param1=null, $param2=null) {
        $nivel = (isset($param1) && Session::get('nivel') < $param1)?$param1:Session::get('nivel');
        $estructura = ( (Session::get('nivel') == $nivel) && (Session::get('estructura') != $param2) )?Session::get('estructura'):$param2;
        if ($nivel == 5) {
            Router::redirect('reportar/unidad/'.$estructura);
        } else {
            $this->nivel = $nivel;
            switch ($nivel) {
                case 1: // Nacional
                    echo "<h3>Regiones</h3>";
                    $this->lista = Load::model('region')->listarConActividades();
                    break;
                case 2: // Regional
                    echo "<h3>Distritos</h3>";
                    $this->lista = Load::model('distrito')->listarConActividades($estructura);
                    break;
                case 3: // Distrital
                    echo "<h3>Grupos</h3>";
                    $this->lista = Load::model('grupos')->listarConActividades($estructura );
                    break;
                case 4: // Grupal
                    echo "<h3>Unidades</h3>";
                    $this->lista = Load::model('ramas')->listarConActividades($estructura);
                    break;
            }
        }

    }

    public function unidad($param1=null, $param2=null) {

        $this->id = (Session::get('nivel') == 5)?Session::get('estructura'):$param1;

        $ano_actual = date('Y', $this->hoy);
        $this->mes_actual = date('n', $this->hoy);

        $ano = ( !isset($param2) || $ano_actual < $param2 )? $ano_actual : $param2;

        $primer_dia   = $ano."-01-01";
        $ultimo_dia     = $ano."-12-31";
        #timezone_open('America/Caracas');
        $fecha_inicio      = strtotime($primer_dia);
        $fecha_fin        = strtotime($ultimo_dia);
        $n = 1;
        $meses = $this->meses;

        $this->objeto = new StdClass();
        $fechas = Load::model('actividades');

        for($dia = $fecha_inicio; $dia <= $fecha_fin; $dia += $this->segundos_dias){
            if(date("d", $dia) == "01") {
                $act = $fechas->listar($this->id, $ano, date('m',$dia));
                if( count($act) ) {
                    $this->objeto->$meses[date('n',$dia)-1] = $act;
                } else {
                    $this->objeto->$meses[date('n',$dia)-1] = array();
                }
            }
        }

    }

    public function nuevo($param1=null, $param2=null, $param3=null) {
        $fechas = Load::model('actividades');
        $jovenes = Load::model('jovenes');
        $this->id = (Session::get('nivel') == 5)?Session::get('estructura'):$param1;
        $rama = Load::model('ramas')->buscar($this->id);
        if ( $rama->tipo_id == 1 || $rama->tipo_id == 2 ) {
            $this->rama = 12;
        } elseif ( $rama->tipo_id == 3 || $rama->tipo_id == 4 ) {
            $this->rama = 32;
        } else {
            $this->rama = 12;
        }

        $ano_actual = date('Y', $this->hoy);
        $this->mes = (empty($param2))?date('m', $this->hoy):$param2;
        $this->mes = (isset($dia->fecha{2}))?$this->mes:'0'.$this->mes;

        $ano = ( !empty($param2) || $ano_actual < $param3 )? $ano_actual : $param3;

        $this->objeto = $fechas->listar($this->id, $ano, $this->mes);
        $this->jovenes = $jovenes->listar($this->id);
        // print_r($_POST);
        if ( Input::hasPost('registrar') ){
            $lista = Input::post('campo');
            $reporte = Load::model('jovenes_actividades');
            $salida = True;
            // print_r($_POST);

            foreach ($lista as $joven => $actividades) {
                // echo "joven - ".$joven.$actividad;
                foreach ($actividades as $actividad) {
                    // echo "joven = $joven -> $actividad; ";
                    $salida = $salida && $reporte->nuevo($joven, $actividad);
               }
            }
            if ($salida) {
                Flash::valid('Actividades Reportadas con éxito');
                Router::redirect('reportar/unidad/'.$this->id);
            }
        }
    }

    public function informe($param1=null, $param2=null) {
        $this->nivel = Session::get('nivel');
        $this->estructura = Session::get('estructura');
        if($this->nivel >= 5){
            Router::toAction('informe_unidad/'.$this->nivel.'/'.$param1.'/'.$param2);
        }
    }

    public function informe_unidad($unidad, $ano=null, $mes=null) {
        $ano_actual = date('Y', $this->hoy);
        $mes_actual = date('n', $this->hoy);
        $ano = ( !empty($ano) || $ano_actual < $ano )?$ano_actual:$ano;
        $mes = ( !empty($mes) || $mes_actual < $mes )?$mes_actual:$mes;
        // $this->lista = Load::model('jovenes_actividades')->jovenes($unidad, $ano, $mes);
        $creditos = Load::model('jovenes_actividades')->jovenes($unidad, $ano, $mes);
        $this->jovenes = array();
        foreach ($creditos as $item) {
            if (!array_key_exists($item->id, $this->jovenes)) {
                $this->jovenes[$item->id] = new StdClass();
                $this->jovenes[$item->id]->credencial = $item->credencial;
                $this->jovenes[$item->id]->nombre = trim($item->primer_nombre.' '.$item->segundo_nombre).' '.trim($item->primer_apellido.' '.$item->segundo_apellido);
                $this->jovenes[$item->id]->cval = 0;
                $this->jovenes[$item->id]->cac = 0;
            }
            if ($item->cac == 1) {
                $this->jovenes[$item->id]->cac += $item->creditos;
            }
            if ($item->cval == 1) {
                $this->jovenes[$item->id]->cval += $item->creditos;
            }
        }
    }

    public function consulta($region=null, $distrito=null, $grupo=null){
        $mes=null; $ano=null;
        $ano_actual = date('Y', $this->hoy);
        $mes_actual = date('n', $this->hoy);
        $ano = ( !empty($ano) || $ano_actual < $ano )?$ano_actual:$ano;
        $mes = ( !empty($mes) || $mes_actual < $mes )?$mes_actual:$mes;

        $model = Load::model('jovenes_actividades');

        if(empty($region)) {
            $creditos = $model->nacional($ano, $mes);
        } elseif (empty($distrito)) {
            $creditos = $model->region($region, $ano, $mes);
        } elseif (empty($grupo)) {
            $creditos = $model->distrito($distrito, $ano, $mes);
        } else {
            $creditos = $model->grupo($grupo, $ano, $mes);
        }

        if ( count($creditos) != 0 ) {
            $this->jovenes = array();
            foreach ($creditos as $item) {
                if (!array_key_exists($item->id, $this->jovenes)) {
                    $this->jovenes[$item->id] = new StdClass();
                    $this->jovenes[$item->id]->campo = $item->campo;
                    $this->jovenes[$item->id]->campo_nombre = $item->campo_nombre;
                    $this->jovenes[$item->id]->credencial = $item->credencial;
                    $this->jovenes[$item->id]->nombre = trim($item->primer_nombre.' '.$item->segundo_nombre).' '.trim($item->primer_apellido.' '.$item->segundo_apellido);
                    $this->jovenes[$item->id]->cval = 0;
                    $this->jovenes[$item->id]->cac = 0;
                }
                if ($item->cac == 1) {
                    $this->jovenes[$item->id]->cac += $item->creditos;
                }
                if ($item->cval == 1) {
                    $this->jovenes[$item->id]->cval += $item->creditos;
                }
            }
            $salida = array('status'=>'valid', 'jovenes'=>$this->jovenes);
        } else {
            $salida = array('status'=>'error');
        }

        echo json_encode($salida);
    }

}

?>
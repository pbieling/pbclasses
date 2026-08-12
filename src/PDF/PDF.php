<?php

namespace PbClasses\PDF;

/*
  Erweitert am 27.12.2005
  Siehe Kommentar zu public function i25
  25.03.06 - Header-Footer rausgeworfen - wird hier nicht gebraucht

 */
//require_once dirname(dirname(__DIR__)) . '/tcpdf/tcpdf.php';

class PDF extends \TCPDF {

    var $relx = 0;
    var $rely = 0;
    var $h_arr = array();
    var $f_arr = array();
    var $first_pagenr_val = '';
    var $first_pagenr_arr = array();
    var $page_break_flag = false;
    var $memory_y = 0;
    var $last_y = 0;
    protected $config = array();

    public function setConfig($conf) {
        $this->config = $conf;
    }

    public function set_Header_data($data) {
        $this->h_arr = $data;
    }

    public function set_Footer_data($data) {
        $this->f_arr = $data;
    }

    public function set_startxy($x, $y) {
        $this->relx = $x + 0;
        $this->rely = $y + 0;
    }

    public function reset_startxy() {
        $this->relx = 0;
        $this->rely = 0;
    }

    public function set_relxy($x, $y) {
        $this->SetXY($x + $this->relx, $y + $this->rely);
    }

    public function set_relx($x) {
        $this->SetX($x + $this->relx);
    }

    public function set_rely($y) {
        $this->SetY($y + $this->rely);
    }

    public function Header() {
        if (count($this->h_arr)) {
            $this->header_footer($this->h_arr);
        }

        //Klassen, die auf jeder Seite aufgerufen werden
        if (isset($this->config['header_classes'])) {
            $this->handleHeaderClasses($this->config['header_classes']);
        }
    }

    protected function handleHeaderClasses($classNames) {
        if (!is_array($classNames)) {
            $classNames = array($classNames);
        }
        foreach ($classNames as $sgClassName) {
            //new $sgClassName($this->pdf, $this->config);
            new $sgClassName($this, $this->config);
        }
    }

    public function Footer() {
        if (count($this->f_arr)) {
            $this->header_footer($this->f_arr);
        }
    }

    public function header_footer($data_arr) {

        foreach ($data_arr as $field) {
            if (!empty($field['@']['type'])) {

                if (preg_match('/image/i', $field['@']['type'])) {
                    $image = $field['val'];

                    if (isset($this->config['PDF_IMGPATH'])) {
                        $image = $this->config['PDF_IMGPATH'] . $image;
                    }
                    $this->make_image($image, $field['@']);
                } elseif (preg_match('/page/i', $field['@']['type'])) {
                    $page_str = str_replace('{PAGE_NUMBER}', $this->PageNo(), $field['val']);
                    if ((empty($field['@']['no1no']) || $field['@']['no1no'] === 'false' || $field['@']['no1no'] === '0') || $this->PageNo() != 1 || $this->page_break_flag) {
                        $this->make_text($page_str, $field['@']);
                        //$this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
                    } elseif ((!empty($field['@']['no1no']) && $field['@']['no1no'] !== 'false' && $field['@']['no1no'] !== '0' ) and $this->PageNo() == 1) {
                        $this->first_pagenr_val = $page_str;
                        $this->first_pagenr_arr = $field['@'];
                    }
                }
            } else {
                $this->make_text($field['val'], $field['@']);
            }
        }
    }

    public function make_image($img, $param) {
        if (!empty($param['max_y']) and $this->GetY() > $param['max_y']) {
            $this->page_break_flag = true;
            $this->AddPage();
        }

        if (!isset($param['top'])) {
            $param['top'] = $this->GetY();
        }
        if (isset($param['memory_y'])) {
            $this->last_y = $this->GetY();
            $param['top'] = $this->memory_y;
        }
        if (isset($param['save_y'])) {
            $this->memory_y = $this->GetY();
        }
        if (!isset($param['left'])) {
            $param['left'] = $this->GetX();
        }


        if (isset($param['spacetop'])) {
            $param['top'] += $param['spacetop'] + 0;
        }

        if (isset($param['spaceleft'])) {
            $param['left'] += $param['spaceleft'] + 0;
        }
        $this->Image($img, $param['left'], $param['top'], $param['width']);

        if (isset($param['fullheight'])) {
            $this->Ln($param['fullheight'] + 0);
        }
    }

    /*
      public function AcceptPageBreak()
      {
      //Kann im Header ausgewertet werden, um Eine Seitenzahl nur dann zu setzen,
      //wenn es keine Einzelseite ist.
      $this->page_break_flag = true;
      return true;
      }

     */

    public function make_text($text, $param) {
        if (!empty($param['max_y']) and $this->GetY() > $param['max_y']) {
            $this->page_break_flag = true;
            $this->AddPage();
        }

        //Standardwerte f�r Cell und Multicell
        /*
         * TCPDF::Cell 	( 	  	
         *                  $w,
          $h = 0,
          $txt = '',
          $border = 0,
          $ln = 0,
          $align = '',
          $fill = false,
          $link = '',
          $stretch = 0,
          $ignore_min_height = false,
          $calign = 'T',
          $valign = 'M'
          )
         */



        $border = 0;
        $align = 'L';
        $fill = 0;

        $link = '';
        $stretch = 0;
        $ignore_min_height = true; //Standard false Bei Cell wird normalerweise
        //eine Mindesthöhe aufgrund der Schriftgröße definiert
        $calign = 'T';

        $valign = 'M';

        $moveto = 2;

        $html = false;
        $multiline = false;

        if (!empty($param['valign'])) {
            $tmp = trim($param['valign']);
            if (preg_match('/^[TCB]$/i', $tmp)) {
                $valign = strtoupper($tmp);
            }
        }

        if (!empty($param['textalign'])) {

            $tmp = trim($param['textalign']);
            if (preg_match('/^[LCRJ]$/i', $tmp)) {
                $align = strtoupper($tmp);
            }
        }
        //bei Cell in n�chste Zeile
        //0 = Ende der Zelle,
        //2 = darunter ?? testen

        if (isset($param['moveto'])) {
            $tmp = trim($param['moveto']);
            if (preg_match('/^[012]$/i', $tmp)) {
                $moveto = $tmp + 0;
            }
        }
        if (!empty($param['border'])) {
            $tmp = trim($param['border']);
            //1 ist von mir
            if (preg_match('/^[LTRB]{1,4}|1$/i', $tmp)) {
                //Die Methode VCell nutzt die Zahl f�r die Liniendicke
                if ($tmp === '1') {
                    $border = 'LTRB';
                } else {
                    $border = strtoupper($tmp);
                }
            }
        }
        if (!empty($param['borderwidth'])) {
            $borderwidth = trim($param['borderwidth']) + 0;
            if ($borderwidth > 0 and $borderwidth < 100) {
                $this->SetLineWidth($borderwidth);
            }
        }
        if (!empty($param['bordercolor'])) {
            list($r, $g, $b) = $this->check_color($param['bordercolor']);
            $this->SetDrawColor($r, $g, $b);
        }

        if (!empty($param['backgroundcolor'])) {
            list($r, $g, $b) = $this->check_color($param['backgroundcolor']);
            $this->SetFillColor($r, $g, $b);
            $fill = 1; //Schalter ein
        }


        if (isset($param['memory_y'])) {
            $param['top'] = $this->memory_y;
        }
        if (isset($param['save_y'])) {
            $this->memory_y = $this->GetY();
        }
        //Bei absoluter Positionierung
        //Bei relativer Positionierung
        if (!empty($param['top'])) {

            //$this->SetY($param['top']);
            $this->set_rely($param['top']);
        }

        if (!empty($param['margintop'])) {
            $mt = $param['margintop'] + 0;
            $this->SetY($this->GetY() + $mt);
        }

        if (!empty($param['reltop'])) {
            $reltop = $param['reltop'] + 0;
            //test($reltop,__LINE__,1);

            $memoryY = $this->GetY();
            $this->SetY($memoryY + $reltop);
        }

        if (!empty($param['left'])) {
            $this->set_relx($param['left']);
            //$this->SetX($param['left']);
        }
        // Weite der Zelle
        if (!empty($param['width'])) {
            $width = $param['width'];
        } else {
            $width = 0;
        }
        if (!empty($param['height'])) {
            $height = $param['height'];
        } else {
            $height = 5;
        }


        if (!empty($param['markdown']) && $param['markdown'] !== "false") {
            //Text in HTML umwandeln und dann weiter wie HTML
            $text = \Michelf\MarkdownExtra::defaultTransform($text);
            $html = true;
        } elseif (!empty($param['html']) && $param['html'] !== "false") {
            $html = true;
        } elseif (!empty($param['multiline']) && $param['multiline'] !== "false") {
            $multiline = true;
        }
        //Textfarbe definieren
        if (!empty($param['fontcolor'])) {
            list($r, $g, $b) = $this->check_color($param['fontcolor']);
            $this->SetTextColor($r, $g, $b);
        }
        //Schriftgr��e definieren
        if (!empty($param['fontsize'])) {
            $fz = $param['fontsize'] + 0;
            $this->SetFontSize($fz);
        }
        if (!empty($param['fontstyle'])) {
            $style = $param['fontstyle'];
            if ($style == 'N') {
                $style = ''; //leer setzen
            }
        }
        if (!empty($param['font'])) {
            $font = $param['font'];
            if (isset($style)) {
                $this->SetFont($font, $style);
            } else {
                $this->SetFont($font);
            }
        }
        if ($html) {
            $this->writeHTMLCell($width, $height, $this->GetX(), $this->GetY(), $text, 0, $moveto, false, true, $align);
        } elseif ($multiline) {

            //MultiCell(float w, float h, string txt [, mixed border [, string align [, int fill]]])
            $this->MultiCell($width, $height, $text, $border, $align, $fill);
        } else {
            //Cell(float w [, float h [, string txt [, mixed border [, int ln [, string align [, int fill ])
            //$link = '',$stretch = 0,$ignore_min_height = false,$calign = 'T',$valign = 'M' 
            $this->Cell($width, $height, $text, $border, $moveto, $align, $fill, $link, $stretch, $ignore_min_height, $calign, $valign);
        }

        //Bei relativer Verschiebung Ursprungsh�he wieder zur�cksetzen
        //reltop
        if (isset($memoryY)) {
            $this->SetY($memoryY);
        }

        if (isset($param['minlastheight'])) {
            $minh = $this->GetY() + $param['minlastheight'];
            if ($this->last_y < $minh) {
                $this->SetY($minh);
            }
        }
        $this->last_y = $this->GetY();
        //RESET
        $this->SetTextColor(0, 0, 0);
    }

    public function check_color($val) {
        // Hexcodeumrechnung einbauen: #000000;
        list ($r, $g, $b) = explode(',', $val);
        $r = $this->corr_color($r);
        $g = $this->corr_color($g);
        $b = $this->corr_color($b);
        return array($r, $g, $b);
    }

    public function corr_color($v) {
        $v = trim($v) + 0;
        if ($v < 0) {
            $v = 0;
        } elseif ($v > 255) {
            $v = 255;
        }
        return $v;
    }

    /*
      Quelle fpdf.org ===================

      Informations
      Author: Matthias Lau
      License: Freeware
      Description
      This script implements Interleaved 2 of 5 barcodes.
      An Interleaved 2 of 5 barcode contains digits (0 to 9)
      and encodes the data in the width of both bars and spaces.
      It is used primarily in the distribution and warehouse industry.

      i25(float xpos, float ypos, string code [, float basewidth [, float height]])

      xpos: abscissa of barcode
      ypos: ordinate of barcode
      code: value of barcode
      basewidth: corresponds to the width of a wide bar (defaults to 1)
      height: bar height (defaults to 10)

      Note: if the length of the code is not even, a 0 is preprended.

      Example:
      define('FPDF_FONTPATH','font/');
      require('i25.php');

      $pdf=new PDF_i25();
      $pdf->AddPage();
      $pdf->i25(90,40,'12345678');
      $pdf->Output();

      Anmerkung P.B.
      Klasse wird nicht als selbsst�ndige Extension verwendet sondern
      in die Klasse PDF (Extension von FPDF) integriert.

     */

    public function i25($xpos, $ypos, $code, $basewidth = 1, $height = 10, $printnr = true, $rquot = 2) {
        //Die Ratio kann jetzt �ber den letzten Parameter angepasst werden
        //Standard ist 1/2 (rquot ist also der ratio-quotient)
        //basewidth ist leider das breite Modul, nich das schmale!
        //Daher muss basewidth zun�chst mit rquot * schmalesModul berechnet werden.
        $wide = $basewidth;
        $narrow = $basewidth / $rquot;
        //$narrow = $basewidth / 3 ;
        // wide/narrow codes for the digits
        $barChar['0'] = 'nnwwn';
        $barChar['1'] = 'wnnnw';
        $barChar['2'] = 'nwnnw';
        $barChar['3'] = 'wwnnn';
        $barChar['4'] = 'nnwnw';
        $barChar['5'] = 'wnwnn';
        $barChar['6'] = 'nwwnn';
        $barChar['7'] = 'nnnww';
        $barChar['8'] = 'wnnwn';
        $barChar['9'] = 'nwnwn';
        $barChar['A'] = 'nn';
        $barChar['Z'] = 'wn';

        // add leading zero if code-length is odd
        if (strlen($code) % 2 != 0) {
            $code = '0' . $code;
        }
        if ($printnr) {   //Erg�nzung P.B.
            $this->SetFont('Arial', '', 10);
            $this->Text($xpos, $ypos + $height + 4, $code);
        }                //P.B.
        $this->SetFillColor(0);

        // add start and stop codes
        $code = 'AA' . strtolower($code) . 'ZA';

        for ($i = 0; $i < strlen($code); $i = $i + 2) {
            // choose next pair of digits
            $charBar = $code[$i];
            $charSpace = $code[$i + 1];
            // check whether it is a valid digit
            if (!isset($barChar[$charBar])) {
                $this->Error('Invalid character in barcode: ' . $charBar);
            }
            if (!isset($barChar[$charSpace])) {
                $this->Error('Invalid character in barcode: ' . $charSpace);
            }
            // create a wide/narrow-sequence (first digit=bars, second digit=spaces)
            $seq = '';
            for ($s = 0; $s < strlen($barChar[$charBar]); $s++) {
                $seq .= $barChar[$charBar][$s] . $barChar[$charSpace][$s];
            }
            for ($bar = 0; $bar < strlen($seq); $bar++) {
                // set lineWidth depending on value
                if ($seq[$bar] == 'n') {
                    $lineWidth = $narrow;
                } else {
                    $lineWidth = $wide;
                }
                // draw every second value, because the second digit of the pair is represented by the spaces
                if ($bar % 2 == 0) {
                    $this->Rect($xpos, $ypos, $lineWidth, $height, 'F');
                }
                $xpos += $lineWidth;
            }
        }
    }

 
    //todo: vertical als Start und End einbauen
    //https://stackoverflow.com/questions/6759870/tcpdf-rotate-text-cell-in-table-header
    //TCPDF-Lösung


}
<?php

namespace PHPInvoicr;

use Mpdf\Mpdf;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class Invoicr
{

    protected $pathD = __DIR__ . DIRECTORY_SEPARATOR . "DOCX" . DIRECTORY_SEPARATOR;
    protected $pathH = __DIR__ . DIRECTORY_SEPARATOR . "HTML" . DIRECTORY_SEPARATOR;
    protected $pathP = __DIR__ . DIRECTORY_SEPARATOR . "PDF" . DIRECTORY_SEPARATOR;


    /** @var string Invoice template to use */
    protected $template = "simple";

    /** @var ?array $data TEMP DATA TO GENERATE INVOICE */
    protected $data = null;

    /**
     * @var string[] Invoice Company Data
     */
    public $company = [
        "http://localhost/code-boxx-logo.png", // URL TO COMPANY LOGO, FOR HTML INVOICES
        "D:/http/code-boxx-logo.png", // FILE PATH TO COMPANY LOGO, FOR PDF/DOCX INVOICES
        "Company Name",
        "Street Address, City, State, Zip",
        "Phone: xxx-xxx-xxx | Fax: xxx-xxx-xxx",
        "https://your-site.com",
        "doge@your-site.com"
    ];

    /** @var string[] HEADERS - INVOICE #, DATE OF PURCHASE, DUE DATE */
    public $head = [];

    /** @var string[] Billing Address */
    public $billto = [];

    /** @var string[] Shipping Address */
    public $shipto = [];

    /** @var array Invoice Items */
    public $items = [];

    /** @var array Totals */
    public $totals = [];

    /** @var array Notes */
    public $notes = [];


    /**
     * Adds invoice parameters
     *
     * @param string $type type of data (as above - head, billto, items, etc...)
     * @param mixed $data the data to add.
     * @return void
     */
    public function add($type, $data)
    {
        if (!isset($this->$type)) {
            exit("Not a valid data type - $type");
        }
        $this->$type[] = $data;
    }

    /**
     * Sets invoice parameters
     *
     * @param string $type type of data (as above - head, billto, items, etc...)
     * @param mixed $data the data to add.
     * @return void
     */
    public function set($type, $data)
    {
        if (!isset($this->$type)) {
            exit("Not a valid data type - $type");
        }
        $this->$type = $data;
    }

    /**
     * Get invoice data
     *
     * @param string $type type of data (as above - head, billto, items, etc...)
     * @return mixed? data;
     */
    public function get($type)
    {
        if (!isset($this->$type)) {
            exit("Not a valid data type - $type");
        }
        return $this->$type;
    }

    /**
     * Resets the invoice data
     *
     * @return void
     */
    function reset()
    {
        $this->company = [];
        $this->head = [];
        $this->billto = [];
        $this->shipto = [];
        $this->items = [];
        $this->totals = [];
        $this->notes = [];
        $this->template = "simple";
        $this->data = null;
    }

    /**
     * Sets the invoice template
     *
     * @param string $template
     * @return void
     */
    function template($template = "simple")
    {
        $this->template = $template;
    }

    /**
     * Force download the invoice.
     *
     * @param string $file filename
     * @param string $size filesize (a4, a5...)
     * @return void
     */
    function outputDown(string $file = "invoice.html", string $size = "")
    {
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$file\"");
        header("Expires: 0");
        header("Cache-Control: must-revalidate");
        header("Pragma: public");
        if (is_numeric($size)) {
            header("Content-Length: $size");
        }
    }


    /**
     * Output the invoice in HTML
     *
     * @param int $mode 1: show in browser, 2: force download, 3: save on server, 4: show in browser + save png
     * @param string $save output filename.
     * @return void
     */
    function outputHTML($mode = 1, $save = null)
    {
        // (G1) TEMPLATE FILE CHECK
        $fileCSS = $this->pathH . $this->template . ".css";
        $fileHTML = $this->pathH . $this->template . ".php";
        if (!file_exists($fileCSS)) {
            exit("$fileCSS not found.");
        }
        if (!file_exists($fileHTML)) {
            exit("$fileHTML not found.");
        }

        // (G2) GENERATE HTML INTO BUFFER
        ob_start(); ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style><?php readfile($fileCSS); ?></style>
            <?php if ($mode == 4) { ?>
                <script src="invlib/html2canvas.min.js"></script>
                <script>window.onload = () => html2canvas(document.getElementById("invoice")).then(canvas => {
                        let a = document.createElement("a");
                        <?php if ($save === null) {
                        $save = "invoice-" . strtotime("now") . ".png";
                    } ?>
                        a.download = "<?=$save?>";
                        a.href = canvas.toDataURL("image/png");
                        a.click();
                    });</script>
            <?php } ?>
        </head>
        <body>
        <div id="invoice"><?php require $fileHTML; ?></div>
        </body>
        </html>
        <?php
        $this->data = ob_get_contents();
        ob_end_clean();

        // (G3) OUTPUT HTML
        switch ($mode) {
            // (G3-1) OUTPUT ON SCREEN (SAVE TO PNG)
            default:
            case 1:
            case 4:
                echo $this->data;
                break;

            // (G3-2) FORCE DOWNLOAD
            case 2:
                if ($save === null) {
                    $save = "invoice-" . strtotime("now") . ".html";
                }
                $this->outputDown($save, strlen($this->data));
                echo $this->data;
                break;

            // (G3-3) SAVE TO FILE ON SERVER
            case 3:
                if ($save === null) {
                    $save = "invoice-" . strtotime("now") . ".html";
                }
                $stream = @fopen($save, "w");
                if (!$stream) {
                    exit("Error opening the file " . $save);
                } else {
                    fwrite($stream, $this->data);
                    if (!fclose($stream)) {
                        exit("Error closing " . $save);
                    }
                }
                break;
        }
    }


    /**
     * Output to PDF File
     *
     * @param int $mode modes: 1: show in browser, 2: force download, 3: save on server
     * @param string $save output filename.
     * @return void
     * @throws \Mpdf\MpdfException
     */
    function outputPDF($mode = 1, $save = "invoice.pdf")
    {
        $mpdf = new Mpdf;

        // (H2) LOAD TEMPLATE FILE
        $file = $this->pathP . $this->template . ".php";
        if (!file_exists($file)) {
            exit("$file not found.");
        }
        $this->data = "";
        require $file;

        // (H3) OUTPUT
        switch ($mode) {
            // (H3-1) SHOW IN BROWSER
            default:
            case 1:
                $mpdf->Output();
                break;

            // (H3-2) FORCE DOWNLOAD
            case 2:
                $mpdf->Output($save, "D");
                break;

            // (H3-3) SAVE FILE ON SERVER
            case 3:
                $mpdf->Output($save);
                break;
        }
    }

    /**
     * Output to DOCX File
     *
     * @param int $mode modes: 1: force download, 2: save on server
     * @param string $save filename
     * @return void
     * @throws \PhpOffice\PhpWord\Exception\Exception
     */
    function outputDOCX($mode = 1, $save = "invoice.docx")
    {
        $pw = new PhpWord();

        // (I2) LOAD TEMPLATE FILE
        $file = $this->pathD . $this->template . ".php";
        if (!file_exists($file)) {
            exit("$file not found.");
        }
        $this->data = "";
        require $file;

        // (I3) OUTPUT
        switch ($mode) {
            // (I3-1) FORCE DOWNLOAD
            default:
            case 1:
                $this->outputDown($save);
                $objWriter = IOFactory::createWriter($pw, "Word2007");
                $objWriter->save("php://output");
                break;

            // (I3-2) SAVE FILE ON SERVER
            case 2:
                $pw->save($save, "Word2007");
                break;
        }
    }
}

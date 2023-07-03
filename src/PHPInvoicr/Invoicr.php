<?php

namespace PHPInvoicr;

use http\Exception;
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

    /** @var bool Use vendor's templates */
    private $vendorTemplate = true;


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
     * @param string $template Template name or path
     * @param boolean $vendor Use Vendor's templates (set to false to use custom paths)
     * @return void
     */
    function template(string $template = "simple", bool $vendor = true)
    {
        $this->template = $template;
        $this->vendorTemplate = $vendor;
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
        $file = $this->vendorTemplate
            ? $this->pathP . $this->template . ".php"
            : $this->template;
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
        $file = $this->vendorTemplate
            ? $this->pathD . $this->template . ".php"
            : $this->template;
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

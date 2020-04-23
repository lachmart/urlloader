<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<?php
/**
 * Created by PhpStorm.
 * User: Martin
 * Date: 26.02.2018
 * Time: 13:30
 */
const MAX_NACTENI = 100;
 //const CHECK_DUPLICATES_IN_DB = false; //kontroluje databázi, duplikované lokální kódy neukládá. KONTROLA NA NAČTENÍ DUPLIKÁTŮ PODLE HTML ODKAZU JE PROVEDENA VŽDY.
const IS_WRITE_TO_DB = false; //true / false .. zápis do DB nebo výstup na obrazovku;

const SERVERNAME = "localhost";
// const DATABASE  ="url_loader";
const DATABASE  ="lp_test";
const USERNAME = "root";
const PASSWORD = "";

//const HTTP_PATH = "https://www.olupaseka.cz/labPrirucka052017/";
//const HTM_INPUT_FILE = "HVEZDAABVT.htm";

//const HTTP_PATH = "http://localhost/urlloader/htm/";
//const HTM_INPUT_FILE = "HVEZDA.htm";

const HTTP_PATH = "http://www2.ikem.cz/plm_lp/";
const HTM_INPUT_FILE = "HVEZDALADA.htm";

const LANGUAGE = 1;
const USERID = 3;
const CONTENTTYPE = 1;

include('lib/simple_html_dom.php');
include("Article.php");

ini_set('default_charset', 'utf-8');

if(!($html = file_get_html(HTTP_PATH.HTM_INPUT_FILE)))
    die("Connection to url failed: " .HTTP_PATH.HTM_INPUT_FILE);
$links = [] ;
foreach($html->find('a') as $l){
    $link["parrent"] = '';
    $link["name"] = trim(rtrim($l->plaintext));
    $link["link"] = $l->href;
    array_push($links, $link);
}
$parser = new Parser($links);
$parser->parse_all();

class Parser
{
    public $conn;
    public $html;
    public $vyhodnoceno;
    public $duplikatu;
    public $ulozeno;

    public function __construct($html)
    {
        if(IS_WRITE_TO_DB) {
            $this->conn = new mysqli(SERVERNAME, USERNAME, PASSWORD, DATABASE);
            // Check connection
            if ($this->conn->connect_error) {
                die("Connection to database failed: " . $this->conn->connect_error);
            }
            $this->conn->set_charset("utf8");
        }
        $this->vyhodnoceno = 0;
        $this->ulozeno = 0;
        $this->duplikatu = 0;
        $this->html = $html;
      //  var_dump($this->html);

    }

    function parse_all()
    {
        $nactene = array();
        // find all links and parse everyone
        for ($x = 1; $x <= MAX_NACTENI; $x++) {
          $e = array_shift($this->html);
          $this->vyhodnoceno += 1;

              $article = new Article(trim(rtrim($e['name'])),trim(rtrim($e['link'])), $e['parrent']);
              $article->parse(HTTP_PATH.$e['link']);

               if (IS_WRITE_TO_DB)
                  $article->save($this->conn);
               else
                   $article->just_print();
              $this->ulozeno +=1;

              foreach($article->data->find('a') as $a){
                 if(substr( $a->href, 0, 4 ) === "HVEZ")
                 {
                  $child["parrent"] = trim(rtrim($e['link']));
                  $child["name"] = trim(rtrim($a->plaintext));
                  $child["link"] = $a->href;
                  array_push($this->html, $child);
                 }
              }
              $article = null;
              gc_collect_cycles();
              flush() ;


                if(!count($this->html))
                  break;
        }
    }

    function __destruct() {
        echo "Vyhodnoceno:".$this->vyhodnoceno." polozek.<br>";
      //  $this->html->clear();
        unset($this->html);
        if (IS_WRITE_TO_DB)
            $this->conn->close();
    }
}

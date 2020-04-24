<?php
/**
 * Created by PhpStorm.
 * User: Martin
 * Date: 27.02.2018
 * Time: 17:00
 */

date_default_timezone_set('Europe/Prague');
class Article
{
    public $parrent;
    public $htm_link;
    public $nazev;
    public $data;

    public function __construct($nazev, $htm, $parrent)
    {
        $this->nazev = $nazev;
        $this->htm_link = $htm;
        $this->parrent = $parrent;
    }

    /**
     * @param $address
     *
     * Parse one and return object Article or null.
     */
      public function parse($address){

        $dom = file_get_html($address);
        if($dom == false)
        {
            trigger_error("something went wrong with:".$address);
            return NULL;
        }
        $this->data = $dom;

      //  echo($this->nazev);
        //var_dump($dom);
    }

    private function minimize($htm)
    {
      //           echo ("Obsah: " . $this->data->find("body", 0)->innertext);
      $htm = str_replace("&nbsp;", "", $htm);
      $htm = strip_tags($htm, "<h1>, <h2>, <h3>, <a>, <p>, <table>, <tr>, <td>");
      $htm = preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/si",'<$1$2>', $htm);
      //echo ("Obsah: " . preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/si",'<$1$2>', $this->data->find("body", 0)->innertext));
      return $htm;

    }

    function seo_url($name) {
        $url = $name;
        $url = preg_replace('~[^\\pL0-9_]+~u', '-', $url);
        $url = trim($url, "-");
        $url = iconv("utf-8", "us-ascii//TRANSLIT", $url);
        $url = strtolower($url);
        $url = preg_replace('~[^-a-z0-9_]+~', '', $url);
        return $url;
    }

    public function just_print()
    {
        echo "<h3>Název menu: " . $this->nazev . ", HTML adresa: " . $this->htm_link . ", Adresa předka: " . $this->parrent . "</h3>";
        echo "URL: ".$this->seo_url(substr($this->nazev,0,50)).", ";
        if ($this->data){
          $htm = $this->minimize($this->data->find("body", 0)->innertext);
          //$htm = $this->data;
        //  echo ("OBSAH: <br>" . $htm);
        }
        flush() ;
    }

    public function save($conn)
    {
        echo ("<hr>");
        echo "Ukládám Název: " . $this->nazev . ", HTML adresa: " . $this->htm_link. ", Adresa předka: " . $this->parrent;
        // if ($this->data){
        //   $htm = $this->minimize($this->data->find("body", 0)->innertext);
        //   echo ("Obsah: <br>" . $htm);
        // }

        flush();

        $articleId = $this->save_article($conn);
        $menuId = $this->save_www_menu($conn);
        $this->save_connection($conn, $articleId, $menuId);

    }

    private function save_article($conn){
      $sql = "SELECT id FROM article WHERE meta_description='".$this->htm_link."'";
      $result = $conn->query($sql);
      //var_dump($result);

      if ($result->num_rows > 0) {
        $articleId = $result->fetch_assoc()["id"];
        echo "<h4>Článek nalezen v db id: ". $articleId ." nazev: ". $this->nazev ."</h4>";
      }
      else{
        $sql = sprintf("INSERT INTO article(name, name_mr, title, content, create_on, lang_id, create_by, meta_description)
                        VALUES ('%s','%s', '%s', '%s', '%s', '%s', '%s', '%s')", $this->nazev, $this->seo_url($this->nazev), $this->nazev, $conn->real_escape_string($this->minimize($this->data->find("body", 0)->innertext)),  date("Y-m-d H:i:s"), LANGUAGE, USERID,  $this->htm_link);

        if ($conn->query($sql) === TRUE) {
            $articleId = $conn->insert_id;
            $this->check_save($conn,$sql);//echo "\t".$sql.";\n";
            echo "<h4>Článek uložen id: ". $articleId ." nazev: ". $this->nazev ."</h4>";
        } else {
            trigger_error("SQL error: " . $conn->error . "\n" . $sql . "\n\n");
        }
      }
      return $articleId;
    }

    private function save_www_menu($conn){
      // $sql = "SELECT id FROM www_menu WHERE custom_url='".$this->htm_link."'";
      // $result = $conn->query($sql);
      // //var_dump($result);
      // if ($result->num_rows > 0) {
      //   $menuId=$result->fetch_assoc()["id"];
      //   echo "<h4>Menu nalezeno v db id: ". $menuId ." nazev: ". $this->nazev ."</h4>";
      // }
      // else{
      $parentId = 0;
      //parrent
      if($this->parrent){
        $sql = "SELECT id FROM www_menu WHERE meta_description='".$this->parrent."'";
        $result = $conn->query($sql);
        //var_dump($result);
        if ($result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
            $parentId=$row["id"];
          }
        }
          echo "<h4>Parrent menu nalezeno v db id: ".$parentId ."</h4>";
        }
        else{

      }


        $sql = sprintf("INSERT INTO www_menu(name, name_mr, title, meta_description, meta_keywords, lang_id, id_precursor)
                        VALUES ('%s','%s', '%s', '%s', '%s', '%s', '%s')", substr($this->nazev,0,50), $this->seo_url(substr($this->nazev,0,50)), $this->nazev, $this->htm_link, ' ',LANGUAGE, $parentId);

        if ($conn->query($sql) === TRUE) {
            $menuId = $conn->insert_id;
            $this->check_save($conn, $sql);//echo "\t".$sql.";\n";
            echo "<h4>Menu uloženo id: ". $menuId ." nazev: ". $this->nazev ."</h4>";
        } else {
            trigger_error("SQL error: " . $conn->error . "\n" . $sql . "\n\n");
        }
      //}
      return $menuId;
    }


    private function save_connection($conn, $articleId, $menuId){
      $sql = sprintf("INSERT INTO www_content(name, create_on, www_menu_id, content_type, content_id)
                      VALUES ('%s','%s', '%s', '%s', '%s')", $this->nazev, date("Y-m-d H:i:s"), $menuId, CONTENTTYPE, $articleId);

      if ($conn->query($sql) === TRUE) {
          $this->check_save($conn,$sql);//echo "\t".$sql.";\n";
          echo "<h4>Propojení uloženo articleId: ". $articleId ." menuId: ". $menuId ."</h4>";
      } else {
          trigger_error("SQL error: " . $conn->error . "\n" . $sql . "\n\n");
      }
    }

    private function check_save($conn, $sql){
        $war_count = mysqli_warning_count($conn);
        if ($war_count > 0){
            $errors = mysqli_get_warnings($conn);
            for ($i = 0; $i < $war_count; $i++) {
                echo "Havarovalo uložení do db:".(var_dump($errors));
                echo $sql.";<br>";
                $errors->next();
            }
        }
    }

    function __destruct() {
        $this->data->clear();
        unset($this->html);
    }
}
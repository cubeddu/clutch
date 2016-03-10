<?php
namespace Clutch\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

use DOMDocument;
use DOMXPath;


class BaseCommand
{

   function recurse_copy($src,$dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                  $this->recurse_copy($src . '/' . $file,$dst . '/' . $file);
                }
                else {
                    copy($src . '/' . $file,$dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    function replace_tags($string, $vars){
      return str_replace(array_keys($vars), $vars, $string);
    }

    function deleteDirectory($dirPath) {
      if (is_dir($dirPath)) {
        $objects = scandir($dirPath);
        foreach ($objects as $object) {
          if ($object != "." && $object !="..") {
            if (filetype($dirPath . DIRECTORY_SEPARATOR . $object) == "dir") {
              $this->deleteDirectory($dirPath . DIRECTORY_SEPARATOR . $object);
            } else {
              unlink($dirPath . DIRECTORY_SEPARATOR . $object);
            }
          }
        }
        reset($objects);
        rmdir($dirPath);
      }
    }

    function Directory($theme,$bundlezip){
        $cssDir = "html/{$bundlezip}/css";
        $jsDir = "html/{$bundlezip}/js";
        $fontDir = "html/{$bundlezip}/fonts";
        $imgDir = "html/{$bundlezip}/images";
        $themecss = "{$theme}/css";
        $themejs = "{$theme}/js";
        $themefont = "{$theme}/fonts";
        $themeimg = "{$theme}/images";
        $tempInfo = __DIR__.'/../../../template';
        // Create Theme folder.
        if (!file_exists($theme)) {
            mkdir($theme, 0777, true);
        }
        // Move files from zip to new theme.
        if(!$cssDir){
          $output->writeln('<comment>Failed to find CSS folder. make sure you are using the webflow zip</comment>');
          return false;
        }else{
            $css = opendir($cssDir);
          while(false !== ( $file = readdir($css)) ) {
            if ( substr($file, -12) == '.webflow.css'){
              rename( $cssDir.'/'.$file, $cssDir.'/'.$theme.substr($file, -12));
            }
          }
        // Move files from zip rename with theme name.
        $this->recurse_copy($cssDir,$themecss);
        }
        if(!$jsDir){
          $output->writeln('<comment>Failed to find JS folder. make sure you are using the webflow zip</comment>');
          return false;
        }else{
          $this->recurse_copy($jsDir,$themejs);
        }
        if(!$fontDir){
          $output->writeln('<comment>Failed to find Fonts folder. make sure you are using the webflow zip</comment>');
          return false;
        }else{
          $this->recurse_copy($fontDir,$themejs);
        }
        if(!$imgDir){
          $output->writeln('<comment>Failed to find Images folder. make sure you are using the webflow zip</comment>');
          return false;
        }else{
          $this->recurse_copy($imgDir,$themejs);
        }

        $this->recurse_copy($tempInfo,$theme);
        rename($theme.'/info.yml',$theme.'/'.$theme.'.info.yml');
        rename($theme.'/libraries.yml',$theme.'/'.$theme.'.libraries.yml');
        rename($theme.'/template.theme',$theme.'/'.$theme.'.theme');
    }

    function ThemeTemplates($theme, $vars){
      $template = file_get_contents($theme.'/'.$theme.'.info.yml', true);
      $infoYML = $this->replace_tags($template, $vars);
      file_put_contents($theme.'/'.$theme.'.info.yml', $infoYML);

      $template = file_get_contents($theme.'/'.$theme.'.libraries.yml', true);
      $infoYML = $this->replace_tags($template, $vars);
      file_put_contents($theme.'/'.$theme.'.libraries.yml', $infoYML);

      $template = file_get_contents($theme.'/'.$theme.'.theme', true);
      $infoYML = $this->replace_tags($template, $vars);
      file_put_contents($theme.'/'.$theme.'.theme', $infoYML);
    }

    function Components($theme,$htmlfiles,$dataBundle,$bundle){
          $files = array();
    foreach($htmlfiles as &$file){
      $bundle_file_name = basename($file,".html");
      // echo $bundle_file_name."\r\n";
      $html = file_get_contents($file);
      $extracted_info = array(); //array to save all the info in just one array [data-component, data-field, div]
      //Extract data-component and store it to $bundle_names
      $data_bundles = explode($dataBundle.'="', $html);
      $bundle_names = array();
      foreach ($data_bundles as &$data_bundle) {
        $data_bundle = substr($data_bundle, 0, strpos($data_bundle, '"'));
        array_push($bundle_names, $data_bundle);
      }
      $doc = new DOMDocument;
      @$doc->loadHTML($html);
      $xpath = new DOMXPath($doc);
      for ($i = 1; $i < count($bundle_names); $i++) { //Search for each data-component found
        // echo $bundle_names[$i];
        $result = '';
        $query = '//div[@'.$dataBundle.'="' . $bundle_names[$i] . '"]/node()/..';
        $node = $xpath->evaluate($query);
        foreach ($node as $childNode) {
          $result .= $doc->saveHtml($childNode); //store the div block to result line by line;
        }
        $temp = array($bundle_names[$i], $result);
        array_push($extracted_info, $temp);
      }
      $extracted_node = array(); //array to save all the info in just one array [data-component, data-field, div]
      //Extract data-component and store it to $bundle_names
      //Generate files
      $html_filename = basename($file);
      $html_filename = str_replace('.html', '', $html_filename);
      $theme_components = $theme."/".$bundle."/";
      // var_dump($bundle_names);
      if (!file_exists($theme_components)) {
        mkdir($theme_components, 0777, true);
      }
      $page = $theme_components.$bundle.'.yml';
      if(0 < count($bundle_names)){
        $pageBundle = $bundle_file_name . ":\r\n  ";
        $pageBundle .= $bundle.':' . "\r\n      ";
        for ($j = 1; $j < count($bundle_names); $j++) {
          $pageBundle .= $bundle_names[$j] . '' . "\r\n      ";
        }
        $pageBundle .= '' . "\r\n\r\n";
      }
      file_put_contents($page, $pageBundle, FILE_APPEND);

      foreach ($extracted_info as &$info) {
        if (!file_exists($theme_components . $info[0] )) {
          mkdir($theme_components . $info[0] , 0777, true);
        }
        $filename = $theme_components . $info[0] . '/' . $info[0] . '.html.twig';
        file_put_contents($filename, $info[1]);
        // $output->writeln('<comment>'.$filename.' </comment>');
         echo $filename. "\r\n";
      }
    }
    }

}

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

use ZipArchive;
use DOMDocument;
use DOMXPath;


class CreateCommand extends Command {
  protected function configure()
  {
    $this
      ->setName('create:theme')
      ->setDescription('This will generate your components folder')
      ->setDefinition(array(
        new InputOption('zip-file', 'z', InputOption::VALUE_REQUIRED, 'Name of the zip file.'),
        new InputOption('theme-name', 't', InputOption::VALUE_REQUIRED, 'Theme Name'),
        new InputOption('theme-description', 'd', InputOption::VALUE_REQUIRED, 'Theme description'),
      ))        ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $bundlezip = $input->getOption('zip-file');
    if(!$bundlezip){
      $helper = $this->getHelper('question');
      $question = new Question("What is your language?\n", 'english');
      $question = new Question('<info>Please enter the name of the zip file:</info> <comment>[webflow]</comment> ', 'webflow');
      $bundlezip = $helper->ask($input, $output, $question);
    }
    // echo $bundlezip;
    $withZip = $bundlezip. ".zip";

    $theme = $input->getOption('theme-name');
    if(!$theme){
      $helper = $this->getHelper('question');
      $question = new Question('<info>Please enter theme name:</info> <comment>[webflow]</comment> ', 'webflow');
      $theme = $helper->ask($input, $output, $question);
    }

    $themeDesc = $input->getOption('theme-description');
    if(!$themeDesc){
      $helper = $this->getHelper('question');
      $question = new Question('<info>Please enter theme description:</info> <comment>[These is a webflow theme]</comment> ', 'These is a webflow theme');
      $themeDesc = $helper->ask($input, $output, $question);
    }

    // echo $theme;
    $zip = new ZipArchive;
    if ($zip->open($withZip) === TRUE) {
      $zip->extractTo('html/');
      $zip->close();
      $output->writeln('<info>Starting Theme creation process</info>');
    } else {
      $output->writeln('<comment>Failed to open the archive!</comment>');
    }
    $directory = "html/{$bundlezip}/";
    $themeMachine = strtolower(str_replace(" ","_",$theme));
    $cssDir = "html/{$bundlezip}/css";
    $jsDir = "html/{$bundlezip}/js";
    $fontDir = "html/{$bundlezip}/fonts";
    $imgDir = "html/{$bundlezip}/images";
    $themecss = "{$theme}/css";
    $themejs = "{$theme}/js";
    $themefont = "{$theme}/fonts";
    $themeimg = "{$theme}/images";
    $htmlfiles = glob($directory . "*.html");
    function recurse_copy($src,$dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                  recurse_copy($src . '/' . $file,$dst . '/' . $file);
                }
                else {
                    copy($src . '/' . $file,$dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
    if (!file_exists($theme)) {
        mkdir($theme, 0777, true);
      }

    // Move files from zip to new theme.
    recurse_copy($jsDir,$themejs);
    recurse_copy($fontDir,$themefont);
    recurse_copy($imgDir,$themeimg);

    $css = opendir($cssDir);
      while(false !== ( $file = readdir($css)) ) {
        if ( substr($file, -12) == '.webflow.css'){
          rename( $cssDir.'/'.$file, $cssDir.'/'.$theme.substr($file, -12));
        }
      }
    // Move css from zip rename with theme name.
    recurse_copy($cssDir,$themecss);

    $tempInfo = __DIR__.'/../../../template';
    recurse_copy($tempInfo,$theme);
    rename($theme.'/info.yml',$theme.'/'.$theme.'.info.yml');
    rename($theme.'/libraries.yml',$theme.'/'.$theme.'.libraries.yml');
    rename($theme.'/template.theme',$theme.'/'.$theme.'.theme');

    $vars = array(
      '{{themeName}}'=> $theme,
      '{{themeMachine}}'=> $themeMachine,
      '{{themeDescription}}'=> $themeDesc
    );
    function replace_tags($string, $vars){
      return str_replace(array_keys($vars), $vars, $string);
    }

    $template = file_get_contents($theme.'/'.$theme.'.info.yml', true);
    $infoYML = replace_tags($template, $vars);
    file_put_contents($theme.'/'.$theme.'.info.yml', $infoYML);

    $template = file_get_contents($theme.'/'.$theme.'.libraries.yml', true);
    $infoYML = replace_tags($template, $vars);
    file_put_contents($theme.'/'.$theme.'.libraries.yml', $infoYML);

    $template = file_get_contents($theme.'/'.$theme.'.theme', true);
    $infoYML = replace_tags($template, $vars);
    file_put_contents($theme.'/'.$theme.'.theme', $infoYML);



    $files = array();
    foreach($htmlfiles as &$file){
      $bundle_file_name = basename($file,".html");
      // echo $bundle_file_name."\r\n";
      $html = file_get_contents($file);
      $extracted_info = array(); //array to save all the info in just one array [data-bundle, data-field, div]
      //Extract data-bundle and store it to $bundle_names
      $data_bundles = explode('data-bundle="', $html);
      $bundle_names = array();
      foreach ($data_bundles as &$data_bundle) {
        $data_bundle = substr($data_bundle, 0, strpos($data_bundle, '"'));
        array_push($bundle_names, $data_bundle);
      }
      $doc = new DOMDocument;
      @$doc->loadHTML($html);
      $xpath = new DOMXPath($doc);
      for ($i = 1; $i < count($bundle_names); $i++) { //Search for each Data-bundle found
        // echo $bundle_names[$i];
        $result = '';
        $query = '//div[@data-bundle="' . $bundle_names[$i] . '"]/node()/..';
        $node = $xpath->evaluate($query);
        foreach ($node as $childNode) {
          $result .= $doc->saveHtml($childNode); //store the div block to result line by line;
        }
        $fields_names = array(); //process to get the field-names of the div block and store to $fields_names
        $data_fields = explode('data-field="', $result);
        array_shift($data_fields);
        foreach ($data_fields as &$data_field) {
          $data_type = strtolower($data_field);
          if (strpos($data_type, 'data-type') !== false) {
            $data_type = explode('data-type="', $data_type);
            $data_type = substr($data_type[1], 0, strpos($data_type[1], '"'));
          } else {
            $data_type = 'Undefined';
          }

          $data_field = substr($data_field, 0, strpos($data_field, '"'));
          $temp = array($data_field, $data_type);
          array_push($fields_names, $temp);
          //     echo "$data_field -> $data_type<br/>"; //Display the fields names
        }

        $temp = array($bundle_names[$i], $fields_names, $result);
        array_push($extracted_info, $temp);
      }

      //Generate files
      $html_filename = basename($file);
      $html_filename = str_replace('.html', '', $html_filename);
      $theme_components = $theme."/components/";
      // var_dump($bundle_names);
      if (!file_exists($theme_components)) {
        mkdir($theme_components, 0777, true);
      }
      $page = $theme_components.'pages.yml';
      if(0 < count($bundle_names)){
        $pageBundle = $bundle_file_name . ":\r\n  ";
        $pageBundle .= 'Bundles:' . "\r\n      ";
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
        file_put_contents($filename, $info[2]);
        $output->writeln('<comment>'.$filename.' </comment>');
        /* echo count($filename++); */

        $yaml_filename = $theme_components . $info[0]. '/' . $info[0] . '.yml';
        $yaml = $html_filename . '_' . $info[0] . ":\r\n  ";
        $yaml .= 'label: ' . $html_filename . '_' . $info[0] . "\r\n  ";
        $yaml .= 'id: ' . $html_filename . '_' . $info[0] . ":\r\n  ";
        $yaml .= 'fields:' . "\r\n      ";
        foreach ($info[1] as &$field) {
          $yaml .= $field[0] . ':' . "\r\n          ";
          $yaml .= 'label: ' . $field[0] . "\r\n          ";
          $yaml .= 'required: false' . "\r\n          ";
          $yaml .= 'type: ' . $field[1] . "\r\n          ";
          $yaml .= 'cardinality: 1' . "\r\n          ";
          $yaml .= 'custom_storage: false' . "\r\n       ";
        }
        file_put_contents($yaml_filename, $yaml);
      }
    }

    function deleteDirectory($dirPath) {
      if (is_dir($dirPath)) {
        $objects = scandir($dirPath);
        foreach ($objects as $object) {
          if ($object != "." && $object !="..") {
            if (filetype($dirPath . DIRECTORY_SEPARATOR . $object) == "dir") {
              deleteDirectory($dirPath . DIRECTORY_SEPARATOR . $object);
            } else {
              unlink($dirPath . DIRECTORY_SEPARATOR . $object);
            }
          }
        }
        reset($objects);
        rmdir($dirPath);
      }
    }
    deleteDirectory('html');

    $output->writeln('<info>Good Job your theme is ready!</info>');
  }
}


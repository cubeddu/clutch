<?php
namespace Clutch\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use ZipArchive;
use DOMDocument;
use DOMXPath;

class CreateCommand extends Command {
    protected function configure()
    {
        $this
            ->setName('create:components')
            ->setDescription('This will generate your components folder')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
 $zip = new ZipArchive;
    if ($zip->open('webflow.zip') === TRUE) {
      $zip->extractTo('html/');
      $zip->close();
      echo 'Archive extracted to html/ folder!'."\r\n";
    } else {
      echo 'Failed to open the archive!'."\r\n";
    }
    $directory = "html/fit/";
    $htmlfiles = glob($directory . "*.html");
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
      // var_dump($bundle_names);
      if (!file_exists('components/')) {
        mkdir('components/', 0777, true);
      }
      $page = 'components/pages.yml';
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
        if (!file_exists('components/' . $info[0] . '')) {
          mkdir('components/' . $info[0] . '', 0777, true);
        }
        $filename = 'components/' . $info[0] . '/' . $info[0] . '.html.twig';
        file_put_contents($filename, $info[2]);
        echo "$filename \r\n";

        $yaml_filename = 'components/' . $info[0] . '/' . $info[0] . '.yml';
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
    }
}


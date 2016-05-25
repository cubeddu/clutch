<?php
namespace Clutch\Command;

use Symfony\Component\Console\Command\Command;
use DOMDocument;
use DOMXPath;
use Wa72\HtmlPageDom\HtmlPageCrawler;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;



class ClutchCli{

  
  /**
   * Move files from zip to theme
   *
   * @param $src, $dst
   *   $src files from the zipfile
   *   $dst where the files are goint to
   *
   * @return
   *   render files to new location
   */
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

  /**
   * Replace template variables
   * with new theme content
   *
   * @param $string, $vars
   *   replace keys with actual content
   *
   * @return
   *   new template with theme name
   */
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
  function copyWebflowFilesToTheme($tempDir, $themeDir, $theme, $folders_to_copy, $output) {
    foreach($folders_to_copy as $folder) {
      $folderPath = $tempDir.$folder;
      if(!opendir($folderPath)) {
        $output->writeln("<comment>Failed to find $folder folder. make sure you are using the webflow zip</comment>");
        return FALSE;
      }
      if($folder == 'css') {
        $css = opendir($folderPath);
        while(false !== ( $file = readdir($css)) ) {
          if ( substr($file, -12) == '.webflow.css'){
            rename( $folderPath.'/'.$file, $folderPath.'/'.$theme.substr($file, -12));
          }
        }
      }
      // Move files from zip rename with theme name.
      $this->recurse_copy($tempDir . $folder, $themeDir . $folder);
    }
  }
  function generateThemeTemplates($themeDir, $theme_vars) {
    $templatesPath = getcwd() . '/' . drupal_get_path('module', 'clutch') .'/templates/';
    $templates = array('info.yml', 'libraries.yml', 'template.theme');
    foreach($templates as $file) {
      $content = file_get_contents($templatesPath . $file);
      $content = $this->replace_tags($content, $theme_vars);
      if($file == 'template.theme') {
        file_put_contents($themeDir.'/'.$theme_vars['{{themeMachine}}'].'.theme', $content);
      }else {
        file_put_contents($themeDir.'/'.$theme_vars['{{themeMachine}}'].".$file", $content);
      }
    }
  }

  function traverseFiles($temp_folder, $themeDir, $theme, $htmlfiles){
    $pages = array();
    foreach($htmlfiles as $file){
      $file_name = basename($file,".html");
      $page_machine_name = str_replace('-', '_', $file_name);
      $pages[$page_machine_name] = array();
      $this->generateComponents($temp_folder, $themeDir, $theme, $file, $pages, $page_machine_name);
    }
    $dumper = new Dumper();
    $yaml = $dumper->dump($pages, 2);
    file_put_contents($themeDir . '/blocks.yml', $yaml, FILE_APPEND);
  }

  function generateComponents($temp_folder, $themeDir, $theme, $file, &$pages, $page_machine_name) {
    $drupal_types = array('block', 'node', 'menu', 'form');
    foreach($drupal_types as $type) {
      $this->generateComponent($temp_folder, $themeDir, $theme, $file, $pages, $page_machine_name, $type);
    }
  }

  function generateComponent($temp_folder, $themeDir, $theme, $file, &$pages, $page_machine_name, $bundle) {
    $temp_bundle_folder = $temp_folder . $bundle . 's/'; 
    if(!file_exists($temp_bundle_folder)) {
      mkdir($temp_bundle_folder);
    }

    $html = file_get_contents($file);
    $crawler = new HtmlPageCrawler($html);
    $crawler->filterXPath("//*[@data-$bundle]")->each(function(HtmlPageCrawler $node, $i) use ($bundle, $temp_bundle_folder, &$pages, $page_machine_name) {
      $data_bundle = $node->getAttribute("data-$bundle");
      switch($bundle) {
        case 'block':
          $template = $temp_bundle_folder . 'block--block-content--' . str_replace('_', '-', $data_bundle) . '.html.twig';
          $pages[$page_machine_name][] = $data_bundle;
          break;
        case 'node':
          $template = $temp_bundle_folder . 'node--' . str_replace('_', '-', $data_bundle) . '.html.twig';
          break;
        case 'form':
          $template = $temp_bundle_folder . 'form--contact-message--' . str_replace('_', '-', $data_bundle) . '.html.twig';
          break;
        case 'menu':
          $template = $temp_bundle_folder . $data_bundle . '.html.twig';
          break;
      }
      if(!file_exists($template)) {
        file_put_contents($template, $node->saveHTML());
      }
    });
  }
}
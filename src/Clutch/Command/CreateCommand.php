<?php
namespace Clutch\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Clutch\Command\BaseCommand;
use ZipArchive;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DomCrawler\Crawler;
use Wa72\HtmlPageDom\HtmlPageCrawler;

class CreateCommand extends Command {
  protected function configure(){
    $this
      ->setName('generate')
      ->setDescription('This will generate your components folder')
      ->setDefinition(array(
        new InputOption('sitemap-url',
        '-url', InputOption::VALUE_REQUIRED, 'Name of the zip file.'),
        new InputOption('theme-name', 't', InputOption::VALUE_REQUIRED, 'Theme Name'),
        new InputOption('theme-description', 'd', InputOption::VALUE_REQUIRED, 'Theme description'),
      ));
  }

   protected function execute(InputInterface $input, OutputInterface $output) {
    $zipFile = $input->getOption('sitemap-url');
    if(!$zipFile){
      $helper = $this->getHelper('question');
      $question = new Question('<info>Please enter your sitemap URL:</info> <comment>http://YOURSITE.webflow.io/sitemap.xml]</comment> ');
      $zipFile = $helper->ask($input, $output, $question);
    }
    $withZip = $zipFile. ".zip";
    // Theme Name
    $theme = $input->getOption('theme-name');
    if(!$theme){
      $helper = $this->getHelper('question');
      $question = new Question('<info>Please enter theme name:</info> <comment>[webflow]</comment> ', 'webflow');
      $theme = $helper->ask($input, $output, $question);
    }
    
    $themeDesc = 'These is a webflow theme';
    
    $tempPath = getcwd().'/temp';

    $sitemap = file_get_contents(trim($zipFile));
    $crawler = new Crawler($sitemap);
    $nodeValues = $crawler->filter('url')->each(function (Crawler $node, $i) {
      return $node->text();
    });

    foreach($nodeValues as $site) {
      $url = file_get_contents(trim($site));
      $page = new HtmlPageCrawler($url);
      $images = $page
        ->filterXpath('//img')
        ->extract(array('src'));
          foreach ($images as $image) {
              $path = basename($image);
              $imageName = ltrim(stristr($path, '_'), '_');
              $insert = file_put_contents('images', $imageName);
              if (!$insert) {
                  throw new \Exception('Failed to write image');
              }
          }
        // echo $page->filter('div')->addClass('w-section')->setAttribute('data-block', 'that_block');
    }
    $style = $page->filterXpath('//style')->text(). "\r\n";
    // $script = $page->filter('script')->attr('src'). "\r\n";
    $script = $page->filter('script')->extract(array('src'));
      foreach ($script as $src) {
          echo $src;
      }


    $extract_webflow_dir = "$tempPath/$zipFile/";

    $htmlfiles = array();

    $finder = new Finder();
    $finder->files()->name('*.html')->in($extract_webflow_dir);
    foreach ($finder as $file) {
      $htmlfiles[] = $file->getRealpath();
    }
    $theme_machine_name = strtolower(str_replace(" ","_",$theme));
    $root = getcwd();
    $themeDir = "$root/themes/$theme_machine_name/";
    if(!file_exists($themeDir)) {
      mkdir($themeDir, 0777);
    }
    $clutchCLI = new ClutchCli();
    $folders_to_copy = array('blocks','nodes', 'menus', 'forms', 'css', 'js', 'fonts', 'images');
    $clutchCLI->traverseFiles($extract_webflow_dir, $themeDir, $theme_machine_name, $htmlfiles);
    $clutchCLI->copyWebflowFilesToTheme($extract_webflow_dir, $themeDir, $theme_machine_name, $folders_to_copy, $output);
    $theme_vars = array('{{themeName}}'=> $theme,'{{themeMachine}}'=> $theme_machine_name,'{{themeDescription}}'=> $themeDesc);
    $clutchCLI->generateThemeTemplates($themeDir, $theme_vars);
    $clutchCLI->deleteDirectory($tempPath);
    $output->writeln('<comment>'. "\r\n" .'Your theme '.$theme.' is now created.</comment>');      
  }
}
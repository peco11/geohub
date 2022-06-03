<?php

namespace App\Console\Commands;

use App\Traits\ImporterAndSyncTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OutSourceTaxonomyMappingCommand extends Command
{
    use ImporterAndSyncTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geohub:out_source_taxonomy_mapping {endpoint : url to the resource (e.g. https://stelvio.wp.webmapp.it)} {provider : WP, StorageCSV} {--activity : add this flag to map activity taxonomy} {--poi_type : add this flag to map webmapp_category/poi_type taxonomy}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the taxonomies from external resource and creates a mapping file';

    protected $type;
    protected $endpoint;
    protected $activity;
    protected $poi_type;
    protected array $content;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->endpoint = $this->argument('endpoint');
        $this->activity = $this->option('activity');
        $this->poi_type = $this->option('poi_type');
        $provider = $this->argument('provider');

        switch (strtolower($provider)) {
            case 'wp':
                return $this->importerWP();
                break;
            
            case 'storagecsv':
                return $this->importerStorageCSV();
                break;
                    
            default:
                return [];
                break;
        }       
    }


    private function importerWP(){
        if ($this->poi_type) {
            $this->importerWPPoiType();
        }
        if ($this->activity) {
            $this->importerWPActivity();
        }
        if ($this->activity == false && $this->poi_type == false) {
            $this->importerWPPoiType();
            $this->importerWPActivity();
        }

        $this->createMappingFile();
        
    }

    private function importerWPPoiType(){
        $url = $this->endpoint.'/wp-json/wp/v2/webmapp_category';
        $WC = $this->curlRequest($url);
        $input["webmapp_category"] = [];
        foreach ($WC as $c) {
            $title = [];
            $title = [
                explode('_',$c['wpml_current_locale'])[0] => $c['name'],
            ];
            $description = [
                explode('_',$c['wpml_current_locale'])[0] => $c['description'],
            ];
            if(!empty($c['wpml_translations'])) {
                foreach($c['wpml_translations'] as $lang){
                    $locale = explode('_',$lang['locale']);
                    $title[$locale[0]] = $lang['name']; 
                    $cat_decode = $this->curlRequest($lang['source']);
                    $description[$locale[0]] = $cat_decode['description']; 
                }
            }
            $input["webmapp_category"][] = [
                'source_id' => $c['id'],
                'source_title' => $title,
                'source_description' => $description,
                'geohub_identifier' => '',
            ];
        }
        $this->content[] = $input;
    }

    private function importerWPActivity(){
        $url = $this->endpoint.'/wp-json/wp/v2/activity';
        $WC = $this->curlRequest($url);
        $input["activity"] = [];
        foreach ($WC as $c) {
            $title = [];
            $title = [
                explode('_',$c['wpml_current_locale'])[0] => $c['name'],
            ];
            $description = [
                explode('_',$c['wpml_current_locale'])[0] => $c['description'],
            ];
            if(!empty($c['wpml_translations'])) {
                foreach($c['wpml_translations'] as $lang){
                    $locale = explode('_',$lang['locale']);
                    $title[$locale[0]] = $lang['name']; 
                    $cat_decode = $this->curlRequest($lang['source']);
                    $description[$locale[0]] = $cat_decode['description']; 
                }
            }
            $input["activity"][] = [
                'source_id' => $c['id'],
                'source_title' => $title,
                'source_description' => $description,
                'geohub_identifier' => '',
            ];
        }
        $this->content[] = $input;
    }
    
    private function createMappingFile(){
        $path = parse_url($this->endpoint);
        $file_name = str_replace('.','-',$path['host']);
        Storage::disk('mapping')->put($file_name.'.json', json_encode($this->content,JSON_PRETTY_PRINT));
    }
}

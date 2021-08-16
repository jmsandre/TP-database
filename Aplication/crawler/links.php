<?php

    use PHPHtmlParser\Dom;

    require_once dirname(__FILE__) . '/../vendor/autoload.php';

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->load();

    
    // HTTP client
    $http_client = new GuzzleHttp\Client([
        GuzzleHttp\RequestOptions::HTTP_ERRORS => false
    ]);

    $years = (new Year)->get();

    $dom = new Dom;
    
    foreach($years as $year) {
        $url = 'https://www.gov.br/inep/pt-br/areas-de-atuacao/avaliacao-e-exames-educacionais/enade/provas-e-gabaritos/' . $year->ano;
        $contents = $http_client->get($url)->getBody()->getContents();

        $dom->loadStr($contents);
        $list_download = $dom->find('.list-download__row');
        
        foreach($list_download as $list) {
            $title = $list->find('.callout');
            if($title->count() == 0) {
                $title = $list->parent->find('> .callout');
            }

            if($title->count() > 0) {
                $title = $title->find('strong')->text;
                
                $data = new KnowledgeArea;
                $data->where('nome', $title);
                if($data->count() > 0) {
                    $area_id = $data->first()->codigo;

                    $links = $list->find('ul li');
                    foreach($links as $link) {
                        $a = $link->find('a');
                        if($a->count() > 1) {
                            $handle = [];
                            foreach($a as $anchor) {
                                if($anchor->text != '') $handle[] = $anchor;
                            }
                            $a = $handle[0];
                        }

                        $data = new KnowledgeAreaLink;
                        $data->where('codigo_ano', $year->codigo)
                            ->where('codigo_area', $area_id)
                            ->where('nome', $a->text);
                        dump($data->count());
                        if($data->count() == 0) {
                            $data->codigo_ano = $year->codigo;
                            $data->codigo_area = $area_id;
                            $data->nome = $a->text;
                            $data->link = $a->href;
                            $data->insert();
                        }
                    }
                }
            }
        }
    }

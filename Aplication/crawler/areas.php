<?php

    use PHPHtmlParser\Dom;

    require_once dirname(__FILE__) . '/../vendor/autoload.php';

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->load();

    
    // HTTP client
    $http_client = new GuzzleHttp\Client([
        GuzzleHttp\RequestOptions::HTTP_ERRORS => false
    ]);

    /*
        Inicia raspagem dos dados da página. O objetivo inicial é capturar todos os anos
        em que foram realizadas prvas do Enade. Para isso precisamos ler o conteúdo HTML
        da página e tratar com a biblioteca do PHP que faz a busca dos dados.
    */
    $initial_url = 'https://www.gov.br/inep/pt-br/areas-de-atuacao/avaliacao-e-exames-educacionais/enade/provas-e-gabaritos';
    $contents = $http_client->get($initial_url)->getBody()->getContents();
    
    $dom = new Dom;
    $dom->loadStr($contents);
    $tabs = $dom->find('.govbr-tabs .tabs .tab');
    
    $all_years = [];
    foreach($tabs as $i => $tab) {
        if($i === 0) continue;
        $year = $tab->find('a')->text;
        $all_years[] = $year;
    }
    $all_years = array_reverse($all_years);

    /*
        Para cada ano, realizamos uma requisição GET na URL específica para o ano, a fim
        de buscar as áreas de conhecimento e documentos de cada uma destas.

        A requisição feita pelo governo tem como resposta um HTML. Desta forma, é possível
        utilizar a mesma biblioteca para recuperar os documentos.
    */
    foreach($all_years as $year) {
        $url = 'https://www.gov.br/inep/pt-br/areas-de-atuacao/avaliacao-e-exames-educacionais/enade/provas-e-gabaritos/' . $year;
        $contents = $http_client->get($url)->getBody()->getContents();

        $dom->loadStr($contents);
        $areas = $dom->find('#content-core .callout');

        $data = new Year;
        $data->where('ano', $year);
        if($data->count() == 0) {
            $data->ano = $year;
            $data->insert();

            $year_id = $data->lastInsertId();
        } else {
            $year_id = $data->first()->codigo;
        }
        
        foreach($areas as $area) {
            $name = $area->find('strong')->text;

            $data = new KnowledgeArea;
            $data->where('nome', $name);
            if($data->count() == 0) {
                $data->nome = $name;
                if($data->insert()) {
                    $area_id = $data->lastInsertId();
                }
            } else {
                $area_id = $data->first()->codigo;
            }

            if(isset($area_id) && isset($year_id)) {
                $data = new KnowledgeAreaYear;
                $data->where('codigo_area', $area_id)
                    ->where('codigo_ano', $year_id);
                if($data->count() == 0) {
                    $data->codigo_area = $area_id;
                    $data->codigo_ano = $year_id;
                    $data->insert();
                }
            }
        }
    }
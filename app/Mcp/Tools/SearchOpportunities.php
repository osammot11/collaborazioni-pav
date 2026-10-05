<?php

namespace App\Mcp\Tools;

class SearchOpportunities extends PipelineTool
{
    protected string $action = 'search';

    protected string $name = 'search_opportunities';

    protected string $description = 'Cerca opportunità per testo, categoria, fase, esito o follow-up. Risultati paginati, massimo 50 per pagina.';
}

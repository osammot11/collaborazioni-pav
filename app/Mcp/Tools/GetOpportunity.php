<?php

namespace App\Mcp\Tools;

class GetOpportunity extends PipelineTool
{
    protected string $action = 'get';

    protected string $name = 'get_opportunity';

    protected string $description = 'Leggi tutti i campi di una opportunità, la revision corrente e gli ultimi 50 cambi di fase/esito. Prima di un update usa questo strumento.';
}

<?php

namespace App\Mcp\Tools;

class CreateOpportunity extends PipelineTool
{
    protected string $action = 'create';

    protected string $name = 'create_opportunity';

    protected string $description = 'Crea una opportunità dopo consenso esplicito. Richiede nome e UUID request_id. Default: da_contattare, in_corso, situazione forse, importi zero. Non invia email.';
}

<?php

namespace App\Mcp\Tools;

class CreateOpportunity extends PipelineTool
{
    protected string $action = 'create';

    protected string $name = 'create_opportunity';

    protected string $description = 'Crea una opportunità dopo consenso esplicito. Richiede nome e UUID request_id. Default: stato da_contattare, situazione forse, importi zero. Nessun esito separato. Non invia email.';
}

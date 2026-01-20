<?
namespace App\Exports;

use App\Models\Closure;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClosuresExport implements FromCollection, WithHeadings
{
    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Closure::with(['fund','currency','user']);
        if (!empty($this->filters['agent_id'])) $query->where('user_id', $this->filters['agent_id']);
        if (!empty($this->filters['fund_id'])) $query->where('fund_id', $this->filters['fund_id']);
        if (!empty($this->filters['status'])) $query->where('status', $this->filters['status']);
        return $query->get()->map(function($c){
            return [
                'Date' => $c->closed_at->format('d/m/Y'),
                'Agent' => $c->user->name,
                'Caisse' => $c->fund->name,
                'Devise' => $c->currency->abreviation,
                'Montant total' => $c->total_amount,
                'Montant reçu' => $c->received_amount,
                'Statut' => $c->status,
                'Solde' => $c->total_amount - ($c->received_amount ?? 0),
            ];
        });
    }

    public function headings(): array
    {
        return ['Date','Agent','Caisse','Devise','Montant total','Montant reçu','Statut','Solde'];
    }
}

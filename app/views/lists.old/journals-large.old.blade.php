<table class="table">
    <tr>
        <th></th>
    </tr>
    @foreach($journals as $journal)
    <tr>
        <td>
            @if($journal->transactiontype->type == 'Withdrawal')
            <span class="glyphicon glyphicon-arrow-left" title="Withdrawal"></span>
            @endif
            @if($journal->transactiontype->type == 'Deposit')
            <span class="glyphicon glyphicon-arrow-right" title="Deposit"></span>
            @endif
            @if($journal->transactiontype->type == 'Transfer')
            <span class="glyphicon glyphicon-resize-full" title="Transfer"></span>
            @endif
            @if($journal->transactiontype->type == 'Opening balance')
            <span class="glyphicon glyphicon-ban-circle" title="Opening balance"></span>
            @endif
        </td>
        <td>
            bud / cat
        </td>
        <td><a href="#" title="{{{$journal->description}}}">{{{$journal->description}}}</a></td>
        <td>
            @if($journal->transactiontype->type == 'Withdrawal')
            <span class="text-danger">{{mf($journal->transactions[1]->amount,false)}}</span>
            @endif
            @if($journal->transactiontype->type == 'Deposit')
            <span class="text-success">{{mf($journal->transactions[1]->amount,false)}}</span>
            @endif
            @if($journal->transactiontype->type == 'Transfer')
            <span class="text-info">{{mf($journal->transactions[1]->amount,false)}}</span>
            @endif
        </td>
        <td>
            @if($journal->transactions[0]->account->accounttype->description == 'Cash account')
            <span class="text-success">(cash)</span>
            @else
            <a href="#">{{{$journal->transactions[0]->account->name}}}</a>
            @endif
        </td>
        <td>
            @if($journal->transactions[1]->account->accounttype->description == 'Cash account')
            <span class="text-success">(cash)</span>
            @else
            <a href="#">{{{$journal->transactions[1]->account->name}}}</a>
            @endif
        </td>
        <td>
            <div class="btn-group btn-group-xs">
                <a href="{{route('transactions.edit',$journal->id)}}" class="btn btn-default">
                    <span class="glyphicon glyphicon-pencil"></span>
                    <a href="{{route('transactions.delete',$journal->id)}}" class="btn btn-danger">
                        <span class="glyphicon glyphicon-trash"></span>
                    </a>
            </div>
        </td>
    </tr>
    @endforeach
</table>

{{$journals->links()}}
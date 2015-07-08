<?php

namespace App\Console\Commands;

use App\Models\Historicals;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GetDailyFinancialsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stocks:getDailyFinancials';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';

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
     * @return mixed
     */
    public function handle()
    {
        if(Carbon::now()->isWeekDay()){
            $this->info("This process can take several minutes...");
            $this->info("Getting daily financials...");
            $stockCodes = Stock::all()->lists('stock_code');

            $numberOfStocks = count($stockCodes);
			$iterationNumber = 1;
			$maxIterations = ceil($numberOfStocks/100);
			while($iterationNumber <= $maxIterations){
				$dailyRecords = explode("\n", file_get_contents("http://finance.yahoo.com/d/quotes.csv?s=".GetDailyFinancialsCommand::getStockCodeParameter()."&f=sohgl1v"));
				foreach($dailyRecords as $record){
					if($record != null){
						$individualRecord = explode(',', $record);
						$stockCode = substr(explode('.', $individualRecord[0])[0], 1);
						Historicals::insert([
							"stock_code" => $stockCode,
							"date" => date("Y-m-d"),
							"open" => $individualRecord[1],
							"high" => $individualRecord[2],
							"low" => $individualRecord[3],
							"close" => $individualRecord[4],
							"volume" => $individualRecord[5],
							"adj_close" => $individualRecord[4],
							"updated_at" => date("Y-m-d H:i:s")
						]);
					}
				}
				$this->info("Updating... ".round(($iterationNumber)*(100/$maxIterations), 2)."%");
				$iterationNumber++;
			}
            //Re-apply index after insert
            \DB::statement("ALTER TABLE 'historicals' ADD INDEX('stock_code')");
            $this->info("Finished getting daily financials for ".$numberOfStocks. " stocks.");
        }
        else{
            $this->info("This command can only be run on weekdays");
        }
        
    }

    //Gets list of stock codes separated by addition symbols
	private static function getStockCodeParameter(){
		date_default_timezone_set("Australia/Sydney");
		//Limit of 100 at a time due to yahoo's url length limit
		$stockCodeList = Stock::whereNotIn('stock_code', Historicals::distinct()->where('date',date("Y-m-d"))->lists('stock_code'))->take(100)->lists('stock_code');
		$stockCodeParameter = "";
		foreach($stockCodeList as $stockCode){
			$stockCodeParameter .= "+".$stockCode.".AX";
		}
		return substr($stockCodeParameter, 1);
	}
}

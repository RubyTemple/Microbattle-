<?php

namespace Clans;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\level\level;
use pocketmine\level\Position;
class FactionCommands {
	
	public $plugin;
	
	public function __construct(FactionMain $pg) {
		$this->plugin = $pg;
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if($sender instanceof Player) {
			$player = $sender->getPlayer()->getName();
			if(strtolower($command->getName('clan'))) {
				if(empty($args)) {
					$sender->sendMessage($this->plugin->formatMessage(" Per vedere tutti i comandi usa /c help"));
					return true;
				}
				if(count($args == 2)) {
					
					///////////////////////////////// WAR /////////////////////////////////
					
					if($args[0] == "war") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Usa - /c war <clan>"));
							return true;
						}
						if(strtolower($args[1]) == "tp") {
							foreach($this->plugin->wars as $r => $f) {
								$fac = $this->plugin->getPlayerFaction($player);
								if($r == $fac) {
									$x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
									$tper = $this->plugin->war_players[$f][$x];
									$sender->teleport($this->plugin->getServer()->getPlayerByName($tper));
									return;
								}
								if($f == $fac) {
									$x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
									$tper = $this->plugin->war_players[$r][$x];
									$sender->teleport($this->plugin->getServer()->getPlayer($tper));
									return;
								}
							}
							$sender->sendMessage("Devi essere in guerra per usare questo comando!");
							return true;
						}
						if(!(ctype_alnum($args[1]))) {
							$sender->sendMessage($this->plugin->formatMessage("usa le lettere!"));
							return true;
						}
						if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Questo clan non esiste!"));
							return true;
						}
						if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("Devi essere in un clan per usare questo comando!"));
							return true;
						}
						if(!$this->plugin->isLeader($player)){
							$sender->sendMessage($this->plugin->formatMessage("Solo il creatore del clan può startare una guerra!"));
							return true;
						} 
						if(!$this->plugin->areEnemies($this->plugin->getPlayerFaction($player),$args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("il tuo clan non è nemico di §a $args[1]!"));
                            return true;
                        } else {
							$factionName = $args[1];
							$sFaction = $this->plugin->getPlayerFaction($player);
							foreach($this->plugin->war_req as $r => $f) {
								if($r == $args[1] && $f == $sFaction) {
									foreach($this->plugin->getServer()->getOnlinePlayers() as $p) {
										$task = new FactionWar($this->plugin, $r);
										$handler = $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
										$task->setHandler($handler);
										$p->sendMessage("Duello tra §7 $factionName §7vs§7 $sFaction §dè cominciato!");
										if($this->plugin->getPlayerFaction($p->getName()) == $sFaction) {
											$this->plugin->war_players[$sFaction][] = $p->getName();
										}
										if($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
											$this->plugin->war_players[$factionName][] = $p->getName();
										}
									}
									$this->plugin->wars[$factionName] = $sFaction;
									unset($this->plugin->war_req[strtolower($args[1])]);
									return true;
								}
							}
							$this->plugin->war_req[$sFaction] = $factionName;
							foreach($this->plugin->getServer()->getOnlinePlayers() as $p) {
								if($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
									if($this->plugin->getLeader($factionName) == $p->getName()) {
										$p->sendMessage("§e$sFaction vuole fare una guerra, 'Usa /c war $sFaction' per iniziare.");
										$sender->sendMessage("Solicitação de duelo enviada!");
										return true;
									}
								}
							}
							$sender->sendMessage("il creatore del clan da te selezzionato non è online.");
							return true;
						}
					}
						
					/////////////////////////////// CREATE ///////////////////////////////
					
					if($args[0] == "crea") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Use - /clan crea <nome del clan>"));
							return true;
						}
						if(!(ctype_alnum($args[1]))) {
							$sender->sendMessage($this->plugin->formatMessage("Puoi usare solo lettere e numeri!"));
							return true;
						}
						if($this->plugin->isNameBanned($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Este nome é inválido."));
							return true;
						}
						if($this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Questa fazione esiste già"));
							return true;
						}
						if(strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
							$sender->sendMessage($this->plugin->formatMessage("nome troppo lungo."));
							return true;
						}
						if($this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("devi prima lasciare il clan in cui ti trovi ora."));
							return true;
						} else {
							$factionName = $args[1];
							$rank = "Leader";
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", $player);
							$stmt->bindValue(":faction", $factionName);
							$stmt->bindValue(":rank", $rank);
							$result = $stmt->execute();
                            $this->plugin->updateAllies($factionName);
                            $this->plugin->setFactionPower($factionName, $this->plugin->prefs->get("TheDefaultPowerEveryFactionStartsWith"));
							$this->plugin->updateTag($sender->getName());
							$sender->sendMessage($this->plugin->formatMessage("Hai creato il clan ! .", true));
							return true;
						}
					}
					
					/////////////////////////////// INVITE ///////////////////////////////
					
					if($args[0] == "invita") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Use - /c invita <giocatore>"));
							return true;
						}
						if($this->plugin->isFactionFull($this->plugin->getPlayerFaction($player)) ) {
							$sender->sendMessage($this->plugin->formatMessage("Clan pieno. Hai raggiunto il numero massimo di giocatori nel clan."));
							return true;
						}
						$invited = $this->plugin->getServer()->getPlayerExact($args[1]);
                        if(!($invited instanceof Player)) {
							$sender->sendMessage($this->plugin->formatMessage("il giocatore selezionato è §4Offline!"));
							return true;
						}
						if($this->plugin->isInFaction($invited) == true) {
							$sender->sendMessage($this->plugin->formatMessage("Questo giocatore possiede già un clan."));
							return true;
						}
						if($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite")) {
                            if(!($this->plugin->isOfficer($player) || $this->plugin->isLeader($player))){
							    $sender->sendMessage($this->plugin->formatMessage("Solo Leader può invitare i giocatori nel clan."));
							    return true;
                            } 
						}
                        if($invited->getName() == $player){
                            
				            $sender->sendMessage($this->plugin->formatMessage("Non puoi inviare te stesso"));
                            return true;
                        }
						
				        $factionName = $this->plugin->getPlayerFaction($player);
				        $invitedName = $invited->getName();
				        $rank = "Member";
								
				        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
				        $stmt->bindValue(":player", $invitedName);
				        $stmt->bindValue(":faction", $factionName);
				        $stmt->bindValue(":invitedby", $sender->getName());
				        $stmt->bindValue(":timestamp", time());
				        $result = $stmt->execute();
				        $sender->sendMessage($this->plugin->formatMessage("$invitedName ha inviato l invito.", true));
				        $invited->sendMessage($this->plugin->formatMessage("§aIl Clan§3  $factionName. §eti ha invitato, Usa '/clan accetta' ou '/clan rifiuta!", true));
						
					}
					
					/////////////////////////////// LEADER ///////////////////////////////
					
					if($args[0] == "capo") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Usa - /clan capo <membro>"));
							return true;
						}
						if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("Crea un clan prima!"));
                            return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Solo il capo può usare questo comando"));
                            return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("aggiungi almeno un giocatore prima."));
                            return true;
						}		
						if(!($this->plugin->getServer()->getPlayerExact($args[1]) instanceof Player)) {
							$sender->sendMessage($this->plugin->formatMessage("Il giocatore è §cOffline."));
                            return true;
						}
                        if($args[1] == $sender->getName()){
                            
				            $sender->sendMessage($this->plugin->formatMessage("è già capo."));
                            return true;
                        }
				        $factionName = $this->plugin->getPlayerFaction($player);
	
				        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
				        $stmt->bindValue(":player", $player);
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Member");
						$result = $stmt->execute();
	
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", $args[1]);
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Leader");
				        $result = $stmt->execute();
	
	
						$sender->sendMessage($this->plugin->formatMessage("Non sei più capo.", true));
						$this->plugin->getServer()->getPlayerExact($args[1])->sendMessage($this->plugin->formatMessage("§6Sei il nuovo capo del Clan§a \nstiamo parlando di questo clan: $factionName!",  true));
						$this->plugin->updateTag($sender->getName());
						$this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
				    }
					
					/////////////////////////////// PROMOTE ///////////////////////////////
					
					if($args[0] == "promuovi") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Use - /clan promuovi <membro>"));
							return true;
						}
						if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("crea prima un clan."));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Solo il capo può usare questo comando"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Questo giocatore non è nel tuo clan."));
							return true;
						}
                        if($args[1] == $sender->getName()){
                            $sender->sendMessage($this->plugin->formatMessage("Non puoi promuovere te stesso."));
							return true;
                        }
                        
						if($this->plugin->isOfficer($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Questo player è già stato promosso"));
							return true;
						}
						$factionName = $this->plugin->getPlayerFaction($player);
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", $args[1]);
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Officer");
						$result = $stmt->execute();
						$player = $this->plugin->getServer()->getPlayerExact($args[1]);
						$sender->sendMessage($this->plugin->formatMessage("$args[1] §aPromosso!", true));
                        
						if($player instanceof Player) {
						    $player->sendMessage($this->plugin->formatMessage("Sei stato promosso ad un grado superiore nel clan: §7§o$factionName!", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
					}
					
					/////////////////////////////// DEMOTE ///////////////////////////////
					
					if($args[0] == "retrocedi") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Use - /clan retrocedi <membro>"));
							return true;
						}
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("Devi prima creare un clan!"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("solo il capo può usare questo comando"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Questo giocatore non è nel tuo clan!"));
							return true;
						}
						
                        if($args[1] == $sender->getName()){
                            $sender->sendMessage($this->plugin->formatMessage("questo giocatore non può essere retrocesso."));
							return true;
                        }
                        if(!$this->plugin->isOfficer($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Ops c'è qualcosa che non và"));
							return true;
						}
						$factionName = $this->plugin->getPlayerFaction($player);
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", $args[1]);
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Member");
						$result = $stmt->execute();
						$player = $this->plugin->getServer()->getPlayerExact($args[1]);
						$sender->sendMessage($this->plugin->formatMessage("$args[1] §cRebaixado!", true));
						if($player instanceof Player) {
						    $player->sendMessage($this->plugin->formatMessage("Sei stato retrocesso a membro nel clan §d $factionName!", true));
						    $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
					}
					
					/////////////////////////////// KICK ///////////////////////////////
					
					if($args[0] == "kick") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Use - /clan kick <membro>"));
							return true;
						}
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("Crea un clan prima!"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("solo il capo può usare questo comando"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("questo player è Offline!"));
							return true;
						}
                        if($args[1] == $sender->getName()){
                            $sender->sendMessage($this->plugin->formatMessage("Non ci si può cacciare da solo."));
							return true;
                        }
						$kicked = $this->plugin->getServer()->getPlayerExact($args[1]);
						$factionName = $this->plugin->getPlayerFaction($player);
						$this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
						$sender->sendMessage($this->plugin->formatMessage("Membro §4Espulso §edal Clan§6 $args[1]!", true));
                        $this->plugin->subtractFactionPower($factionName,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
						
						if($kicked instanceof Player) {
			                $kicked->sendMessage($this->plugin->formatMessage("sei stato §cEspulso §edal Clan:§6 \n $factionName!",true));
							$this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
							return true;
						}
					}
					
					/////////////////////////////// INFO ///////////////////////////////
					
					if(strtolower($args[0]) == 'info') {
						if(isset($args[1])) {
							if( !(ctype_alnum($args[1])) | !($this->plugin->factionExists($args[1]))) {
								$sender->sendMessage($this->plugin->formatMessage("Clan inesistente"));
							    $sender->sendMessage($this->plugin->formatMessage("Verifica se il nome del clan è scritto in maniera corretta."));
								return true;
							}
							$faction = $args[1];
							$result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
							$array = $result->fetchArray(SQLITE3_ASSOC);
                            $power = $this->plugin->getFactionPower($faction);
							$message = $array["message"];
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::YELLOW . "§a§o-------§4§oInformazioni§a-------".TextFormat::RESET);
							$sender->sendMessage(TextFormat::RESET . TextFormat::RESET . "§d<Clan> " . TextFormat::GRAY . "$faction".TextFormat::RESET);
							$sender->sendMessage(TextFormat::RESET . TextFormat:: RESET . "§e-*Capo*- " . TextFormat::GOLD . "$leader".TextFormat::RESET);
							$sender->sendMessage(TextFormat::RESET . TextFormat::RESET . "§c- (Membri) - " . TextFormat::DARK_RED . "$numPlayers".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::RESET . "§a= Potere = - " . TextFormat::DARK_GREEN . "$power" . " §aSTR".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::AQUA . "|*Descrizione*| - " . TextFormat::GOLD . TextFormat::UNDERLINE . "§3$message".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::YELLOW . "§a§o-------§4Informazioni§o-------".TextFormat::RESET);
						} else {
                            if(!$this->plugin->isInFaction($player)){
                                $sender->sendMessage($this->plugin->formatMessage("Crea un clan prima!"));
                                return true;
                            }
							$faction = $this->plugin->getPlayerFaction(($sender->getName()));
							$result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
							$array = $result->fetchArray(SQLITE3_ASSOC);
                            $power = $this->plugin->getFactionPower($faction);
							$message = $array["message"];
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::YELLOW . "§a§o-------§4Info del clan§a-------".TextFormat::RESET);
							$sender->sendMessage(TextFormat::RESET . TextFormat::RESET . "§d<Clan> " . TextFormat::GRAY . "§5$faction".TextFormat::RESET);
							$sender->sendMessage(TextFormat::RESET . TextFormat::RESET . "§e*Capo* " . TextFormat::GOLD . "$leader".TextFormat::GRAY);
							$sender->sendMessage(TextFormat::RESET . TextFormat::WHITE . "- (Membri) - " . TextFormat::GRAY . "$numPlayers".TextFormat::RESET);
							$sender->sendMessage(TextFormat::RESET . TextFormat::GREEN . "+Potere+ " . TextFormat::DARK_GREEN . "$power" . " §aSTR".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::AQUA . "|*Descrizione*| - " . TextFormat::BLUE . TextFormat::UNDERLINE . "§3$message".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::YELLOW . "§a§o-------§4Info del clan§a-------".TextFormat::RESET);
						}
					}
					if(strtolower($args[0]) == "help") {
						if(!isset($args[1]) || $args[1] == 1) {
							$sender->sendMessage(TextFormat::GOLD . "§a==== §eComandi Clan §a==== §c(1/6)" . TextFormat::YELLOW . "\n§6/c crea <nome> §7Per( creare un clan)\n§6/c Plugin §7(Per avere informazioni sul plugin)\n§6/c accetta §7(Per accettare gli inviti nei clan\n§6/c rifiuta §7(Per rifiutare l invito di un clan)\n§6/c war <clan> §7(Per iniziare una guerra tra )\n§6/c info §7(Per avere informazioni su un clan)\n§6/c invita §7(Per invitare qualcuno nel tuo clan");
							return true;
						}
						if($args[1] == 2) {
							$sender->sendMessage(TextFormat::GOLD . "§a==== §eComandi Clan §a==== §c(2/6)" . TextFormat::RED . "\n§6/c kick <membro> §7(Per espellere un membro)\n§6/c capo <membro> §7(Per mettere capo qualcuno)\n§6/c lascia §7(Per abbandonare un clan)");
							return true;
						} 
                        if($args[1] == 3) {
							$sender->sendMessage(TextFormat::GOLD . "§a==== §eComandi Clan §a==== §c(3/6)" . TextFormat::RED . "\n§6/c rimuovi (clan) §7(Per rimuovere il tuo clan) \n§6/c esci (clan) §7(Per uscire dal clan) \n§6/c sethome §7(Per settare la base al tuo clan) \n§6/c unsethome §7(Per levare la base al tuo clan) \n§6/c base §7(Per andare alla base del clan)");
							return true;
						} 
                        if($args[1] == 4) {
                            $sender->sendMessage(TextFormat::GOLD . "§a==== §eComandi Clan §a==== §c(4/6)" . TextFormat::RED . "\n§6/c desc (msg) §7(Per mettere una descrizione al tuo clan)\n§6/c promuovi <membro> §7(Per promuovere qualcuno del tuo caln)\n§6/c allea <clan> §7(Per alleare 2 o più clan)\n§6/c unallea <clan> §7(Per levare l alleanza con un clan)\n/c si §7(Per accettare un alleanza)\n/c no §7(Per negare una richiesta di alleanza)\n§6/c alleati <clan> §7(Per vedere gli alleati di un clan)\n§6/c ac §7(Per attivare la chat con gli alleati)");
							return true;
                        } 
                        if($args[1] == 5){
                            $sender->sendMessage(TextFormat::GOLD . "§a==== §eClans Comandos §a==== §c(5/6)" . TextFormat::RED . "\n§6/c pf <player>\n§6/c top §7(Per vedere i clan migliori\n§6/c chat §7(Per parlare solo al tuo clan)");
							return true;
                        }
                        else {
                            $sender->sendMessage(TextFormat::GOLD . "§aParte per lo staff" . TextFormat::RED . "\n/c domina <clan> solo Op\n/c fc <clan> solo OP\n/c addpw <clan> <PODER> Add poder solo OP ");
							return true;
                        }
					}
				}
				if(count($args == 1)) {
					
					/////////////////////////////// CLAIM ///////////////////////////////
					
					if(strtolower($args[0]) == 'claim') {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Devi avere un clan."));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("devi essere il capo per usare questo comando."));
							return true;
						}
                        
						if($this->plugin->inOwnPlot($sender)) {
							$sender->sendMessage($this->plugin->formatMessage("Il tuo clan domina già su questo territorio."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
                        if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){
                           
                           $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") - 
                                               $this->plugin->getNumberOfPlayers($faction);
                           $sender->sendMessage($this->plugin->formatMessage("§7Ti  servono più giocatori per settare il claim §f $needed_players"));
				           return true;
                        }
                        if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
							$sender->sendMessage($this->plugin->formatMessage("Il tuo Clan non ha abbastanza potere per dominare una terra."));
							$sender->sendMessage($this->plugin->formatMessage("serve $needed_power §6di potere per claimare, ma il tuo clan attualmente ne ha solo§e $faction_power."));
                            return true;
                        }
						
                        $x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
						if($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {
                            
							return true;
						}
                        
						$sender->sendMessage($this->plugin->formatMessage("§aSto prendendo le coordinate...", true));
                        $plot_size = $this->plugin->prefs->get("PlotSize");
                        $faction_power = $this->plugin->getFactionPower($faction);
						$sender->sendMessage($this->plugin->formatMessage("Terra Dominata.", true));
					
					}
                    if(strtolower($args[0]) == 'plotinfo'){
                        $x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
                        if(!$this->plugin->isInPlot($sender)){
                            $sender->sendMessage($this->plugin->formatMessage("Terreno libero reclama usando§3/clan claim", true));
							return true;
                        }
                        
                        $fac = $this->plugin->factionFromPoint($x,$z);
                        $power = $this->plugin->getFactionPower($fac);
                        $sender->sendMessage($this->plugin->formatMessage("§7Questa terra possiede già un proprietario §5$fac poder $power poder"));
                    }
                    if(strtolower($args[0]) == 'top'){
                        $this->plugin->sendListOfTop10FactionsTo($sender);
                    }
                    if(strtolower($args[0]) == 'fc') {
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Use - /c fc <clan>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("La funzione richiesta non esiste."));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("La funzione richiesta non esiste."));
                            return true;
						}
						$this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
						$this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
				        $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
				        $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
                        $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
						$this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
				        $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
				        $sender->sendMessage($this->plugin->formatMessage("Clan Eliminato!", true));
                    }
                    if(strtolower($args[0]) == 'addpw') {
                        if(!isset($args[1]) or !isset($args[2])){
                            $sender->sendMessage($this->plugin->formatMessage("Use - /c addpw <clan> <PODER>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("La funzione richiesta non esiste."));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("La funzione richiesta non esiste."));
                            return true;
						}
                        $this->plugin->addFactionPower($args[1],$args[2]);
				        $sender->sendMessage($this->plugin->formatMessage("Ho messo $args[2] di potere al clan§6 - $args[1]", true));
                    }
                    if(strtolower($args[0]) == 'pf'){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Use - /c pf <player>"));
                            return true;
                        }
                        if(!$this->plugin->isInFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Questa fazione nn esiste."));
							$sender->sendMessage($this->plugin->formatMessage("Assicurati che il nome di questo giocatore esista."));
                            return true;
						}
                        $faction = $this->plugin->getPlayerFaction($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("-$args[1] è in $faction-",true));
                        
                    }
                    
                    if(strtolower($args[0]) == 'overclaim') {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Crea un clan."));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Solo i capi possono usare questo comando."));
							return true;
						}
                        $faction = $this->plugin->getPlayerFaction($player);
						if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){
                           
                           $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") - 
                                               $this->plugin->getNumberOfPlayers($faction);
                           $sender->sendMessage($this->plugin->formatMessage("Você precisa $needed_players jogadores para dominar o terreno"));
				           return true;
                        }
                        if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
							$sender->sendMessage($this->plugin->formatMessage("Il tuo Clan non ha abbastanza potere per governare questa terra."));
							$sender->sendMessage($this->plugin->formatMessage("$needed_power è il potere richiesto per riscvattare questo terreno, ma il tuo clan ne ha solo $faction_power poder."));
                            return true;
                        }
						$sender->sendMessage($this->plugin->formatMessage("§aPegando coordenadas...", true));
						$x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
                        if($this->plugin->prefs->get("EnableOverClaim")){
                            if($this->plugin->isInPlot($sender)){
                                $faction_victim = $this->plugin->factionFromPoint($x,$z);
                                $faction_victim_power = $this->plugin->getFactionPower($faction_victim);
                                $faction_ours = $this->plugin->getPlayerFaction($player);
                                $faction_ours_power = $this->plugin->getFactionPower($faction_ours);
                                if($this->plugin->inOwnPlot($sender)){
                                    $sender->sendMessage($this->plugin->formatMessage("§aTerra dominata."));
                                    return true;
                                } else {
                                    if($faction_ours_power < $faction_victim_power){
                                        $sender->sendMessage($this->plugin->formatMessage("Non puoi usare l overclaim\n questa terra è del Clan§6 $faction_victim \n§eHanno più potere del tuo."));
                                        return true;
                                    } else {
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                                        $arm = (($this->plugin->prefs->get("PlotSize")) - 1) / 2;
                                        $this->plugin->newPlot($faction_ours,$x+$arm,$z+$arm,$x-$arm,$z-$arm);
						                $sender->sendMessage($this->plugin->formatMessage("Il Terreno del clan $faction_victim  è stato dominato... Ora è tuo.", true));
                                        return true;
                                    }
                                    
                                }
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("Devi prima creare un clan."));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("È disabilitato l'Overclaiming."));
                            return true;
                        }
                        
					}
                    
					
					/////////////////////////////// UNCLAIM ///////////////////////////////
					
					if(strtolower($args[0]) == "unclaim") {
                        if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("Devi avere un clan."));
							return true;
						}
						if(!$this->plugin->isLeader($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("solo il capo può usare questo comando."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
						$sender->sendMessage($this->plugin->formatMessage("Terreno abandonato .", true));
					}
					
					/////////////////////////////// DESCRIPTION ///////////////////////////////
					
					if(strtolower($args[0]) == "desc") {
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("Devi avere un clan!"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("solo il capo può usare questo comando"));
							return true;
						}
						$sender->sendMessage($this->plugin->formatMessage("Inserisci il tuo messaggio nella chat. Non sarà visibile agli altri giocatori", true));
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
						$stmt->bindValue(":player", $sender->getName());
						$stmt->bindValue(":timestamp", time());
						$result = $stmt->execute();
					}
					
					/////////////////////////////// ACCEPT ///////////////////////////////
					
					if(strtolower($args[0]) == "accetta") {
						$player = $sender->getName();
						$lowercaseName = ($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("em... non hai inviti al momento!"));
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if(($currentTime - $invitedTime) <= 60) { //il tempo può essere cambiato
							$faction = $array["faction"];
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", ($player));
							$stmt->bindValue(":faction", $faction);
							$stmt->bindValue(":rank", "Member");
							$result = $stmt->execute();
							$this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("Entrou no CLAN§6 $faction!", true));
                            $this->plugin->addFactionPower($faction,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player ti sei unito al clan.", true));
							$this->plugin->updateTag($sender->getName());
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Invito scaduto!"));
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$player';");
						}
					}
					
					/////////////////////////////// DENY ///////////////////////////////
					
					if(strtolower($args[0]) == "rifiuta") {
						$player = $sender->getName();
						$lowercaseName = ($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("non hai inviti ora!"));
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $invitedTime) <= 60 ) { //puoi modificare il tempo
							$this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("invito negato!", true));
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player Ha negato l'invito."));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("invito scaduto!"));
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
						}
					}
					
					/////////////////////////////// DELETE ///////////////////////////////
					
					if(strtolower($args[0]) == "rimuovi") {
						if($this->plugin->isInFaction($player) == true) {
							if($this->plugin->isLeader($player)) {
								$faction = $this->plugin->getPlayerFaction($player);
                                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
								$this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
								$this->plugin->db->query("DELETE FROM allies WHERE faction1='$faction';");
								$this->plugin->db->query("DELETE FROM allies WHERE faction2='$faction';");
								$this->plugin->db->query("DELETE FROM strength WHERE faction='$faction';");
								$this->plugin->db->query("DELETE FROM motd WHERE faction='$faction';");
								$this->plugin->db->query("DELETE FROM home WHERE faction='$faction';");
								$sender->sendMessage($this->plugin->formatMessage("§cClan eliminato", true));
								$this->plugin->updateTag($sender->getName());
							} else {
								$sender->sendMessage($this->plugin->formatMessage("Solo il capo può usare questo comando!"));
							}
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Devi avere un clan per farlo!"));
						}
					}
					
					/////////////////////////////// LEAVE ///////////////////////////////
					
					if(strtolower($args[0] == "esci")) {
						if($this->plugin->isLeader($player) == false) {
							$remove = $sender->getPlayer()->getNameTag();
							$faction = $this->plugin->getPlayerFaction($player);
							$name = $sender->getName();
							$this->plugin->db->query("DELETE FROM master WHERE player='$name';");
							$sender->sendMessage($this->plugin->formatMessage("Sei uscito dal  CLAN - §8 $faction", true));
                            
                            $this->plugin->subtractFactionPower($faction,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
							$this->plugin->updateTag($sender->getName());
						} else {
							$sender->sendMessage($this->plugin->formatMessage("È necessario eliminare il CLAN, o dare la leadership a qualcun altro."));
						}
					}
					
					/////////////////////////////// SETHOME ///////////////////////////////

					if(strtolower($args[0] == "sethome")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("devi avere un clan."));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("sem perm."));
							return true;
						}
						$factionName = $this->plugin->getPlayerFaction($sender->getName());
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z) VALUES (:faction, :x, :y, :z);");
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":x", $sender->getX());
						$stmt->bindValue(":y", $sender->getY());
						$stmt->bindValue(":z", $sender->getZ());
						$result = $stmt->execute();
						$sender->sendMessage($this->plugin->formatMessage("Casa settata!", true));
					}

					/////////////////////////////// UNSETHOME ///////////////////////////////

					if(strtolower($args[0] == "unsethome")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Devi avere un clan."));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("non hai i perm per farlo."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
						$sender->sendMessage($this->plugin->formatMessage("coordinate casa dimenticate!", true));
					}

					/////////////////////////////// HOME ///////////////////////////////

					if(strtolower($args[0] == "base")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("devi avere un clan."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(!empty($array)){
							$sender->getPlayer()->teleport(new Position($array['x'], $array['y'], $array['z'], $this->plugin->getServer()->getLevelByName("Factions")));
							$sender->sendMessage($this->plugin->formatMessage("ti sto teletrasportando....", true));
						} 
						else{
							$sender->sendMessage($this->plugin->formatMessage("base non settata."));
						}
					}
                    
                    /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
                    if(strtolower($args[0] == "recluta")){
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("devi avere un clan."));
                            return true;
						}
                        $this->plugin->getPlayersInFactionByRank($sender,$this->plugin->getPlayerFaction($player),"Member");
                       
                    }
                    if(strtolower($args[0] == "mof")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Use - /c mof <Clan>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Este Clan Não Existe."));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender,$args[1],"Member");
                       
                    }
                    if(strtolower($args[0] == "onf")){
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("crie Um Clan."));
                            return true;
						}
                        $this->plugin->getPlayersInFactionByRank($sender,$this->plugin->getPlayerFaction($player),"Officer");
                    }
                    if(strtolower($args[0] == "Off")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Use - /c Off <clan>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Este CLAN não existe."));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender,$args[1],"Officer");
                       
                    }
                    if(strtolower($args[0] == "onl")){
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Crie Um clan."));
                            return true;
						}
                        $this->plugin->getPlayersInFactionByRank($sender,$this->plugin->getPlayerFaction($player),"Leader");
                    }
                    if(strtolower($args[0] == "ofl")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Use - /c ofl <clan>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("CLAN não encontrado."));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender,$args[1],"Leader");
                       
                    }
                    if(strtolower($args[0] == "say")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Use - /c say <mensagem>"));
                            return true;
                        }
                        if(!($this->plugin->isInFaction($player))){
                            
                            $sender->sendMessage($this->plugin->formatMessage("Crie Um Clan"));
                            return true;
                        }
                        $r = count($args);
                        $row = array();
                        $rank = "";
                        $f = $this->plugin->getPlayerFaction($player);
                        
                        if($this->plugin->isOfficer($player)){
                            $rank = "*";
                        } else if($this->plugin->isLeader($player)){
                            $rank = "**";
                        }
                        $message = "-> ";
                        for($i=0;$i<$r-1;$i=$i+1){
                            $message = $message.$args[$i+1]." "; 
                        }
                        $result = $this->plugin->db->query("SELECT * FROM master WHERE faction='$f';");
                        for($i=0;$resultArr = $result->fetchArray(SQLITE3_ASSOC);$i=$i+1){
                            $row[$i]['player'] = $resultArr['player'];
                            $p = $this->plugin->getServer()->getPlayerExact($row[$i]['player']);
                            if($p instanceof Player){
                                $p->sendMessage(TextFormat::RED.TextFormat::RED."-F§dSay-".TextFormat::GOLD." [$rank$f] ".TextFormat::YELLOW."[$player] ".": ".TextFormat::RESET);
                                $p->sendMessage(TextFormat::WHITE.TextFormat::WHITE.$message.TextFormat::RESET);
                                
                            }
                        } 
                            
                    }
                    
                  
                    ////////////////////////////// ALLY SYSTEM ////////////////////////////////
					if(strtolower($args[0] == "alleati")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Use /clan alleati <clan>"));
                            return true;
                        }
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("devi avere un clan."));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("solo il capo può usare questo comando."));
                            return true;
						}
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Clan non trovato."));
                            return true;
						}
                        if($this->plugin->getPlayerFaction($player) == $args[1]){
                            $sender->sendMessage($this->plugin->formatMessage("§csiete nemici."));
                            return true;
                        }
                        if($this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§6il tuo Clan è §cnemico §6di§e $args[1]!"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
						$leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        
                        if(!($leader instanceof Player)){
                            $sender->sendMessage($this->plugin->formatMessage("capo offline."));
                            return true;
                        }
                        $this->plugin->setEnemies($fac, $args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("sei nemico di $args[1]!",true));
                        $leader->sendMessage($this->plugin->formatMessage("il capo del clan $fac è nemico del tuo.",true));
                        
                    }
                    if(strtolower($args[0] == "allea")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Use - /c allea <clan>"));
                            return true;
                        }
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Devi avere un clan."));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Solo il capo può usare questo comando."));
                            return true;
						}
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Clan inesistente."));
                            return true;
						}
                        if($this->plugin->getPlayerFaction($player) == $args[1]){
                            $sender->sendMessage($this->plugin->formatMessage("Il tuo clan ha poco potere non può ancora ottenere alleanze."));
                            return true;
                        }
                        if($this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§ail tuo clan si è alleato con§6 $args[1]!"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
						$leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);
                        
                        if(!($leader instanceof Player)){
                            $sender->sendMessage($this->plugin->formatMessage("capo offline."));
                            return true;
                        }
                        if($this->plugin->getAlliesCount($args[1])>=$this->plugin->getAlliesLimit()){
                           $sender->sendMessage($this->plugin->formatMessage("limite raggiunto.",false));
                           return true;
                        }
                        if($this->plugin->getAlliesCount($fac)>=$this->plugin->getAlliesLimit()){
                           $sender->sendMessage($this->plugin->formatMessage("Limite raggiunto.",false));
                           return true;
                        }
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
				        $stmt->bindValue(":player", $leader->getName());
				        $stmt->bindValue(":faction", $args[1]);
				        $stmt->bindValue(":requestedby", $sender->getName());
				        $stmt->bindValue(":timestamp", time());
				        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("§eAvete chiesto §adi allearvi §econ $args[1]!\n§6aspetta che il capo dell'altro clan risponda...",true));
                        $leader->sendMessage($this->plugin->formatMessage("§eil capo del clan §6$fac §eVuole fare un alleanza.\nUsa /c si per accettare l'invito /clan no per rrifiutare l'invito.",true));
                        
                    }
                    if(strtolower($args[0] == "unallea")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage(" Use /clan unallea <clan>"));
                            return true;
                        }
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Devi prima creare un clan."));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("solo il capo può usare questo comando."));
                            return true;
						}
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Clan non trovato."));
                            return true;
						}
                        if($this->plugin->getPlayerFaction($player) == $args[1]){
                            $sender->sendMessage($this->plugin->formatMessage("§cIl tuo clan non può rompere l'alleanza con se stesso."));
                            return true;
                        }
                        if(!$this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Il tuo clan non è alleato con§7 $args[1]!"));
                            return true;
                        }
                        
                        $fac = $this->plugin->getPlayerFaction($player);        
						$leader= $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->deleteAllies($fac,$args[1]);
                        $this->plugin->deleteAllies($args[1],$fac);
                        $this->plugin->subtractFactionPower($fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->subtractFactionPower($args[1],$this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("Il clan§6 $fac §eha levato l'alleanza con§6 $args[1]!",true));
                        if($leader instanceof Player){
                            $leader->sendMessage($this->plugin->formatMessage("il capo del clan§6 $fac ha rotto l'alleanza con il tuo clan§6 $args[1]!",false));
                        }
                        
                        
                    }
                    if(strtolower($args[0] == "domina")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Use - /c domina <clan>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("CLAN non trovato"));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("Solo OP's."));
                            return true;
						}
				        $sender->sendMessage($this->plugin->formatMessage("Terreno dominato $args[1]!"));
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                        
                    }
                    
                    if(strtolower($args[0] == "alleati")){
                        if(!isset($args[1])){
                            if(!$this->plugin->isInFaction($player)) {
							    $sender->sendMessage($this->plugin->formatMessage("crea un clan prima."));
                                return true;
						    }
                            
                            $this->plugin->updateAllies($this->plugin->getPlayerFaction($player));
                            $this->plugin->getAllAllies($sender,$this->plugin->getPlayerFaction($player));
                        } else {
                            if(!$this->plugin->factionExists($args[1])) {
							    $sender->sendMessage($this->plugin->formatMessage("Clan inesistente."));
                                return true;
						    }
                            $this->plugin->updateAllies($args[1]);
                            $this->plugin->getAllAllies($sender,$args[1]);
                            
                        }
                        
                    }
                    if(strtolower($args[0] == "si")){
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("devi prima creare un clan."));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("comando solo per il capo clan."));
                            return true;
						}
						$lowercaseName = ($player);
						$result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("il tuo clan non ha mai avuto richieste di alleanze!"));
							return true;
						}
						$allyTime = $array["timestamp"];
						$currentTime = time();
						if(($currentTime - $allyTime) <= 60) { //il tempo può essere cambiato
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
							$this->plugin->setAllies($requested_fac,$sender_fac);
							$this->plugin->setAllies($sender_fac,$requested_fac);
                            $this->plugin->addFactionPower($sender_fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->addFactionPower($requested_fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
							$this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $this->plugin->updateAllies($requested_fac);
                            $this->plugin->updateAllies($sender_fac);
							$sender->sendMessage($this->plugin->formatMessage("Il tuo CLAN è alleato con $requested_fac!", true));
							$this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player da $sender_fac §aha accettato l alleanza!", true));
                            
                            
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Richiesta scaduta!"));
							$this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
						}
                        
                    }
                    if(strtolower($args[0]) == "no") {
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("crea un clan prima."));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("Solo il capo può usare questo comando."));
                            return true;
						}
						$lowercaseName = ($player);
						$result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("§aalleanza respinta."));
							return true;
						}
						$allyTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $allyTime) <= 60 ) { //è modificabile
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
							$this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("Alleanza negata.", true));
							$this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player da $sender_fac ha rifiutato l'alleanza!"));
                            
						} else {
							$sender->sendMessage($this->plugin->formatMessage("tempo esaurito!"));
							$this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
						}
					}
                           
                    
					/////////////////////////////// ABOUT ///////////////////////////////
					
					if(strtolower($args[0] == 'plugin')) {
						$sender->sendMessage(TextFormat::GREEN . "[ORIGINALE] Clan v2.0.0.2 by " . TextFormat::BOLD . "Emis");
						$sender->sendMessage(TextFormat::GOLD . "[MODIFICATO in] Clans by " . TextFormat::BOLD . "Sempre Emis XD");
					}
					////////////////////////////// CHAT ////////////////////////////////
					if(strtolower($args[0]) == "chat" or strtolower($args[0]) == "c"){
						if($this->plugin->isInFaction($player)){
							if(isset($this->plugin->factionChatActive[$player])){
								unset($this->plugin->factionChatActive[$player]);
								$sender->sendMessage($this->plugin->formatMessage("Chat disattivata!", false));
								return true;
							}
							else{
								$this->plugin->factionChatActive[$player] = 1;
								$sender->sendMessage($this->plugin->formatMessage("§aChat attiva.", false));
								return true;
							}
						}
						else{
							$sender->sendMessage($this->plugin->formatMessage("crea un clan prima"));
							return true;
						}
					}
					if(strtolower($args[0]) == "ac" or strtolower($args[0]) == "ac"){
						if($this->plugin->isInFaction($player)){
							if(isset($this->plugin->allyChatActive[$player])){
								unset($this->plugin->allyChatActive[$player]);
								$sender->sendMessage($this->plugin->formatMessage("chat con l alleanze disattivato §cdisattivata!", false));
								return true;
							}
							else{
								$this->plugin->allyChatActive[$player] = 1;
								$sender->sendMessage($this->plugin->formatMessage("§achat con le allenaze §ain funzione!", false));
								return true;
							}
						}
						else{
							$sender->sendMessage($this->plugin->formatMessage("crea un clan prima"));
							return true;
						}
					}
				}
			}
		} else {
			$this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("plugin attivo by §eEmis §fdi §aIta§fSky§4Games - usa all interno del gioco"));
		}
	}

}
## About PolyCash
PolyCash is an open source blockchain protocol for peer to peer betting currencies.  PolyCash integrates with blockchains including Bitcoin, Litecoin, Dogecoin & [Datachain](https://github.com/datachains/datachain). The PolyCash protocol powers Betcoin, a digital currency with a novel inflation model where coins are given out to players for betting on virtual basketball games in addition to being given to miners for securing the network.

## Install PolyCash
You can try PolyCash by creating a web wallet on a public node like [https://poly.cash](https://poly.cash).  But installing PolyCash on your own computer helps decentralize our network and allows you to control your own private keys.

PolyCash runs on Apache, MySQL and PHP.  To get started quickly, we recommend installing PolyCash with Docker.  You can download Docker Desktop [here](https://www.docker.com/products/docker-desktop/).

Once Docker Desktop is installed and running, open a terminal and clone the PolyCash repository:
```
git clone https://github.com/PolyCash/polycash.git
cd polycash
```

Next, build your Docker container:
```
docker-compose -f docker-compose.yml up --build
```

Next, open your browser and navigate to:
```
http://localhost:8080/
```

PolyCash will automatically install and begin syncing with the network.  The synchronization step can take hours to complete. 

If PolyCash hasn't started already, open Docker Desktop, navigate to the Containers section and then click the start/play icon on your polycash container.


## Install Blockchains & Games
By default, the Betcoin cryptocurrency is installed when you install PolyCash.  You can install other PolyCash-protocol cryptocurrencies by pasting their game definitions in via the "Import" link found in the left menu.

To get the betcoin cryptocurrency in sync, you'll need to install the Datachain blockchain as a full node. If you're using Docker, datachain should have already been installed for you. If you are not running PolyCash with Docker, you may need to compile Datachain for your operating system. To do that, visit [Datachain](https://github.com/datachains/datachain) on github.

Check if datachain is running by opening the terminal for your polycash-app-1 Docker container and running this command:
```
cd /var/www/html
./datacoin-cli getblockchaininfo
```

If datachain is not running, start it:
```
cd /var/www/html
./datacoind &
```

## Set Blockchain RPC credentials

Next you need to enter your Datachain RPC credentials into PolyCash. To do that, log in to the PolyCash user account you used when installing, then click the "Manage Blockchains" link in the left hand menu. Then select Datachain -> "Set RPC credentials", and then enter the RPC username and password from your datacoin.conf (typically located at /.datacoin/datacoin.conf).

Enter these values for Datachain:
```
RPC hostname: 127.0.0.1
RPC username: datacoinuser
RPC password: datacoinpass
RPC port: 9023
Status: Enabled
Sync mode: Full
Sync from block: 1
```

## Make PolyCash Configuration Changes

To make changes to your PolyCash installation, open your configuration file at polycash/src/config/config.json
> /src/config/config.json

## Configure currency price oracles

For full functionality in synthetic assets game, you should configure external price oracles for your currencies.  To configure oracles, enter configuration records in the "oracles" section of your src/config/config.json. By default, the coin-desk oracle should be configured for updating the Bitcoin price. The CoinDesk Bitcoin price API does not require an API key, but other oracles may.  If you have installed the Forex 128 game, you can use fcsapi.com as an oracle for currency prices.  After paying for FCS API, enter your API key in your oracle configuration by replacing MyApiKey as shown in this example configuration:
```
	"oracles": [
		{
			"selector_type": "single",
			"currency": "BTC",
			"oracle": "coin-desk"
		},
		{
			"selector_type": "group",
			"group": "32 highly traded currencies",
			"oracle": "fcs-api",
			"api_key": "MyApiKey"
		}
	]
```

You can modify the frequency of price refreshing by modifying the "currency_price_refresh_seconds" value in your src/config/config.json

You can run the price fetch script manually with this command:
```
bash
cd /var/www/html
php src/cron/fetch_currency_prices.php force=1
```

## MySQL Configuration

If you have installed PolyCash with a method other than the default Docker installation, you may need to change MySQL configuration for good performance. You can increase the max allowed packet size, increase the allowed seconds for timeout and disable bin logs to avoid excessive disk usage. To do this, edit your MySQL configuration file which is typically located at /etc/mysql/conf.d/mysql.cnf
```
[mysqld]
max_allowed_packet=1G
innodb_lock_wait_timeout=1000
disable_log_bin
```

## Cron Jobs
To add cron jobs to your docker container, open your .dockerize/cron folder and create a copy of the "polycash-crontab-example"; name the copy "polycash-crontab". Cronjobs entered in either file will run but the "polycash-crontab" file is not included in this repository so can be used for your own jobs.  These crontabs are copied to /etc/cron.d when docker starts so please restart your container after making changes.

## Change PolyCash Memory Limit

If you run into memory problems, try changing your PolyCash process memory limit.  Edit src/config/config.json and set the "memory_limit" attribute to a value like "4096M"

## Make Datacoin Configuration Changes

If you need to make changes to your datachain configuration, open the terminal for your Docker container and stop datachain.
```
./datacoin-cli stop
```
Next, edit your /.datacoin/datacoin.conf file.
Then restart datacoind.
```
/var/www/html/datacoind &
```

## Run PolyCash Commands

To run PolyCash commands, open a terminal for your polycash-app-1 Docker container, navigate to your polycash folder and then run a php command.
```
bash
cd /var/www/html
php src/cron/load_blocks.php print_debug=1
```

## Running a Public Node

If you installed PolyCash using the recommended method, your node has been configured for local use with no public access.  If you would like to run a public PolyCash node, you should make some changes to your configuration before going live with your node.

Edit src/config/config.json:
- Create a secure random string and enter it as "operator_key"
- Set "desktop_mode": false,
- Delete lines for "only_user_username" and "only_user_password"

By default, Datachain RPC calls can only be run locally from your node.  You can allow RPC access from other IPs by adding "rpcallowip" to your datacoin.conf.  When running a public node be sure to set secure RPC credentials by editing "rpcuser" and setting a secure random string for "rpcpassword" in your /.datacoin/datacoin.conf

## Shutting Down Safely

It's recommended to always stop Datachain before shutting down your node. To stop Datachain open a terminal for your polycash-app-1 container and then run this command:

```
/var/www/html/datacoin-cli stop
```

Once datacoin has stopped, it's safe to stop your Docker container.

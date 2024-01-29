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

To start or stop PolyCash, simply open Docker Desktop, navigate to the Containers section and then use the start and stop buttons.

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

If you installed with Docker, PolyCash should have been configured to use the right RPC credentials for Datachain. If not, you may need to enter your Datachain RPC credentials into PolyCash. To do that, log in to the PolyCash user account you used when installing, then click the "Manage Blockchains" link in the left hand menu. Then select Datachain -> "Set RPC credentials", and then enter the RPC username and password from your datacoin.conf (typically located at /.datacoin/datacoin.conf).

## Make PolyCash Configuration Changes

To make changes to your PolyCash installation, open your configuration file at polycash/src/config/config.json
> /src/config/config.json

### Change PolyCash Memory Limit

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

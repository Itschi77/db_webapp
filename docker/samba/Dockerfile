FROM debian:13-slim

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends samba smbclient \
    && useradd --no-create-home --shell /usr/sbin/nologin rechnungen \
    && rm -rf /var/lib/apt/lists/*

COPY smb.conf /etc/samba/smb.conf
COPY samba-entrypoint.sh /usr/local/bin/samba-entrypoint.sh
RUN chmod 0755 /usr/local/bin/samba-entrypoint.sh

EXPOSE 139 445

ENTRYPOINT ["/usr/local/bin/samba-entrypoint.sh"]

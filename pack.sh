rm integration-for-szamlazzhu-fluentcart.zip
cd ..
zip integration-for-szamlazzhu-fluentcart/integration-for-szamlazzhu-fluentcart.zip -r integration-for-szamlazzhu-fluentcart \
   --exclude="integration-for-szamlazzhu-fluentcart/.git/*" \
   --exclude="integration-for-szamlazzhu-fluentcart/tests/*" \
   --exclude="integration-for-szamlazzhu-fluentcart/*.zip" \
   --exclude="integration-for-szamlazzhu-fluentcart/*.md" \
   --exclude="integration-for-szamlazzhu-fluentcart/*.sh"
cd integration-for-szamlazzhu-fluentcart